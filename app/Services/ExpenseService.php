<?php

namespace App\Services;

use App\Models\BudgetAllocation;
use App\Models\Category;
use App\Models\Expense;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    public function getUserExpenses(int $userId, array $filters = []): Collection
    {
        $visibleIds = BudgetAccessService::visibleBudgetIds($userId);

        $query = Expense::where(function ($q) use ($userId, $visibleIds) {
                $q->where('user_id', $userId);
                if (!empty($visibleIds)) {
                    // Shared-budget expenses (created by owner or other editors).
                    $q->orWhereIn('budget_id', $visibleIds);
                }
            })
            ->with(['category', 'subcategory', 'budget'])
            ->orderBy('date', 'desc');

        if (!empty($filters['budget_id'])) {
            $query->where('budget_id', $filters['budget_id']);
        }

        if (!empty($filters['category_id'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('category_id', $filters['category_id'])
                    ->orWhere('subcategory_id', $filters['category_id']);
            });
        }

        return $query->get();
    }

    /**
     * Phase 1: direct expense entry with automatic budget deduction.
     *
     * Business rules enforced atomically:
     *  1. amount > 0
     *  2. category belongs to user (or is a system default)
     *  3. subcategory (if given) belongs to the category
     *  4. budget (if given) belongs to the user
     *  5. allocated_amount is NEVER modified — only spent_amount is derived
     *  6. whole operation runs in a DB transaction (rollback on any failure)
     */
    public function createExpense(int $userId, array $data): Expense
    {
        $amount = (float) ($data['amount'] ?? 0);
        if (!is_numeric($data['amount'] ?? null) || $amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be a positive number.']);
        }

        $category = $this->resolveUserCategory($userId, (int) $data['category_id'], $this->budgetOwnerId($data['budget_id'] ?? null));

        $subcategory = null;
        if (!empty($data['subcategory_id'])) {
            $subcategory = Category::find($data['subcategory_id']);
            if (!$subcategory || (int) $subcategory->parent_id !== (int) $category->id) {
                throw ValidationException::withMessages([
                    'subcategory_id' => 'Subcategory does not belong to the given category.',
                ]);
            }
        }

        $budgetId = $data['budget_id'] ?? null;
        if ($budgetId) {
            $this->assertEditableBudget($userId, (int) $budgetId);
        }

        $source = $data['source'] ?? 'manual';
        if (!in_array($source, ['manual', 'voice'], true)) {
            $source = 'manual';
        }

        return DB::transaction(function () use ($userId, $data, $amount, $budgetId, $source) {
            $expense = Expense::create([
                'user_id' => $userId,
                'category_id' => $data['category_id'],
                'subcategory_id' => $data['subcategory_id'] ?? null,
                'budget_id' => $budgetId,
                'amount' => $amount,
                'currency_code' => $data['currency_code'] ?? 'USD',
                'date' => $data['date'],
                'description' => $data['description'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'source' => $source,
            ]);

            if ($budgetId) {
                // Refresh spent totals for both the parent category and the
                // subcategory allocation rows (if they exist). Allocation rows
                // are never created with allocated_amount=0 here — spent is
                // only written onto existing rows so planned amounts survive.
                $this->refreshSpentAmount((int) $budgetId, (int) $data['category_id']);
                if (!empty($data['subcategory_id'])) {
                    $this->refreshSpentAmount((int) $budgetId, (int) $data['subcategory_id']);
                }
            }

            return $expense->fresh(['category', 'subcategory', 'budget']);
        });
    }

    public function updateExpense(Expense $expense, array $data): Expense
    {
        if (isset($data['amount'])) {
            $amount = (float) $data['amount'];
            if (!is_numeric($data['amount']) || $amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Amount must be a positive number.']);
            }
        }

        if (isset($data['category_id'])) {
            $this->resolveUserCategory($expense->user_id, (int) $data['category_id'], $this->budgetOwnerId($data['budget_id'] ?? $expense->budget_id));
        }

        $effectiveCategoryId = (int) ($data['category_id'] ?? $expense->category_id);
        if (array_key_exists('subcategory_id', $data) && !empty($data['subcategory_id'])) {
            $subcategory = Category::find($data['subcategory_id']);
            if (!$subcategory || (int) $subcategory->parent_id !== $effectiveCategoryId) {
                throw ValidationException::withMessages([
                    'subcategory_id' => 'Subcategory does not belong to the given category.',
                ]);
            }
        }

        if (array_key_exists('budget_id', $data) && !empty($data['budget_id'])) {
            $this->assertEditableBudget($expense->user_id, (int) $data['budget_id']);
        }

        if (isset($data['source']) && !in_array($data['source'], ['manual', 'voice'], true)) {
            unset($data['source']);
        }

        $oldBudgetId = $expense->budget_id;
        $oldCategoryId = $expense->category_id;
        $oldSubcategoryId = $expense->subcategory_id;

        return DB::transaction(function () use ($expense, $data, $oldBudgetId, $oldCategoryId, $oldSubcategoryId) {
            $expense->update($data);
            $expense->refresh();

            // Re-derive spent totals for every touched allocation row.
            foreach (array_unique(array_filter([
                $oldBudgetId ? [$oldBudgetId, $oldCategoryId] : null,
                $oldBudgetId && $oldSubcategoryId ? [$oldBudgetId, $oldSubcategoryId] : null,
                $expense->budget_id ? [$expense->budget_id, $expense->category_id] : null,
                $expense->budget_id && $expense->subcategory_id ? [$expense->budget_id, $expense->subcategory_id] : null,
            ]), SORT_REGULAR) as [$bId, $cId]) {
                $this->refreshSpentAmount((int) $bId, (int) $cId);
            }

            return $expense->fresh(['category', 'subcategory', 'budget']);
        });
    }

    public function deleteExpense(Expense $expense): void
    {
        $budgetId = $expense->budget_id;
        $categoryId = $expense->category_id;
        $subcategoryId = $expense->subcategory_id;

        DB::transaction(function () use ($expense, $budgetId, $categoryId, $subcategoryId) {
            $expense->delete();

            if ($budgetId) {
                $this->refreshSpentAmount((int) $budgetId, (int) $categoryId);
                if ($subcategoryId) {
                    $this->refreshSpentAmount((int) $budgetId, (int) $subcategoryId);
                }
            }
        });
    }

    /**
     * Re-derive spent_amount = SUM(expenses) for one allocation row.
     * Never touches allocated_amount, never creates zero-allocated rows.
     */
    protected function refreshSpentAmount(int $budgetId, int $categoryId): void
    {
        $totalSpent = (float) Expense::where('budget_id', $budgetId)
            ->where(function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId)
                    ->orWhere('subcategory_id', $categoryId);
            })
            ->sum('amount');

        $allocation = BudgetAllocation::where('budget_id', $budgetId)
            ->where('category_id', $categoryId)
            ->first();

        if ($allocation) {
            $allocation->update(['spent_amount' => $totalSpent]);
        }
        // Intentionally NOT creating a row: allocations are the plan,
        // expenses are actuals. Unplanned categories surface via the
        // budget summary as spent-without-allocation instead of
        // fabricating a zero-allocation row (previous bug wiped plans).
    }

    protected function resolveUserCategory(int $userId, int $categoryId, ?int $budgetOwnerId = null): Category
    {
        $category = Category::find($categoryId);
        if (!$category) {
            throw ValidationException::withMessages(['category_id' => 'Category not found.']);
        }
        // Partner flow: a category is usable if it is a system default, owned
        // by the actor, or owned by the budget owner (shared budget).
        $allowed = [$userId];
        if ($budgetOwnerId !== null) {
            $allowed[] = $budgetOwnerId;
        }
        $owns = $category->user_id === null || in_array((int) $category->user_id, $allowed, true);
        if (!$owns) {
            throw ValidationException::withMessages(['category_id' => 'Category does not belong to this user.']);
        }

        return $category;
    }

    protected function budgetOwnerId(mixed $budgetId): ?int
    {
        if (empty($budgetId)) {
            return null;
        }
        return (int) \App\Models\Budget::where('id', $budgetId)->value('user_id') ?: null;
    }

    protected function assertUserBudget(int $userId, int $budgetId): void
    {
        $this->assertEditableBudget($userId, $budgetId);
    }

    /**
     * Write access: owner or accepted editor of the budget.
     */
    protected function assertEditableBudget(int $userId, int $budgetId): void
    {
        $budget = \App\Models\Budget::find($budgetId);
        if (!$budget || !BudgetAccessService::canEdit($budget, $userId)) {
            throw ValidationException::withMessages(['budget_id' => 'Budget does not belong to this user.']);
        }
    }
}
