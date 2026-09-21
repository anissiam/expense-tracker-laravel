<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\BudgetAllocation;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BudgetService
{
    public function getUserBudgets(int $userId): Collection
    {
        $budgets = Budget::where('user_id', $userId)
            ->orWhereIn('id', function ($q) use ($userId) {
                $q->select('budget_id')
                    ->from('budget_members')
                    ->where('user_id', $userId)
                    ->where('status', \App\Models\BudgetMember::STATUS_ACCEPTED);
            })
            ->orderBy('start_date', 'desc')
            ->get();

        // Annotate sharing info for the frontend (owner/editor/viewer).
        return $budgets->map(function (Budget $b) use ($userId) {
            $b->setAttribute('my_role', $b->userRole($userId));
            $b->setAttribute('is_shared', (int) $b->user_id !== $userId);
            return $b;
        });
    }
    
    public function getBudget(int $userId, int $year, int $month): ?Budget
    {
        return Budget::where('user_id', $userId)
            ->whereYear('start_date', $year)
            ->whereMonth('start_date', $month)
            ->first();
    }

    public function createBudget(int $userId, array $data): Budget
    {
        $carbon = Carbon::createFromDate($data['year'], $data['month'], 1);

        $exists = Budget::where('user_id', $userId)
            ->whereYear('start_date', $data['year'])
            ->whereMonth('start_date', $data['month'])
            ->exists();
            
        if ($exists) {
            throw new \Exception('A budget for this month and year already exists.');
        }

        return Budget::create([
            'user_id' => $userId,
            'name' => $carbon->format('F Y') . ' Budget',
            'type' => $data['mode'] ?? 'salary',
            'total_amount' => $data['income'],
            'currency_code' => $data['currency'] ?? 'USD',
            'start_date' => $carbon->toDateString(),
            'end_date' => $carbon->endOfMonth()->toDateString(),
            'status' => 'active',
        ]);
    }
    
    public function updateBudget(Budget $budget, array $data): Budget
    {
        $budget->update($data);
        return $budget;
    }

    /**
     * Phase 1: active monthly budget summary with overspend math.
     * remaining = allocated - spent (negative => overspent, flagged).
     */
    public function getActiveMonthlyBudget(int $userId, ?int $year = null, ?int $month = null): array
    {
        $visibleIds = BudgetAccessService::visibleBudgetIds($userId);
        $query = Budget::whereIn('id', $visibleIds)->where('status', 'active');

        if ($year && $month) {
            $query->whereYear('start_date', $year)->whereMonth('start_date', $month);
        }

        $budget = $query->orderBy('start_date', 'desc')->first()
            ?? Budget::whereIn('id', $visibleIds)->orderBy('start_date', 'desc')->first();

        if (!$budget) {
            return [
                'budget' => null,
                'total_allocated' => 0,
                'total_spent' => 0,
                'remaining' => 0,
                'is_overspent' => false,
                'categories' => [],
            ];
        }

        return $this->recalculateBudgetValues($budget->id) + ['budget' => $budget->fresh()];
    }

    /**
     * remaining = allocated - spent per allocation and in total.
     * Allocated amounts are read-only inputs; this only derives spent/remaining.
     *
     * @return array{total_allocated: float, total_spent: float, remaining: float, is_overspent: bool, categories: array}
     */
    public function recalculateBudgetValues(int $budgetId): array
    {
        $allocations = BudgetAllocation::with('category')
            ->where('budget_id', $budgetId)
            ->get();

        // Single source of truth for actuals: SUM(expenses). Allocation rows
        // only carry the plan (allocated_amount); spent is derived per row
        // from matching expenses so parent rows aggregate their sub expenses.
        $totalSpent = (float) \App\Models\Expense::where('budget_id', $budgetId)->sum('amount');

        $categories = $allocations->map(function ($a) use ($budgetId) {
            $allocated = (float) $a->allocated_amount;
            $spent = (float) \App\Models\Expense::where('budget_id', $budgetId)
                ->where(function ($q) use ($a) {
                    $q->where('category_id', $a->category_id)
                        ->orWhere('subcategory_id', $a->category_id);
                })
                ->sum('amount');
            $remaining = $allocated - $spent;

            return [
                'category_id' => $a->category_id,
                'category_name' => $a->category?->name,
                'allocated' => $allocated,
                'spent' => $spent,
                'remaining' => $remaining,
                'is_overspent' => $remaining < 0,
            ];
        })->all();

        $totalAllocated = (float) $allocations->sum('allocated_amount');
        $remaining = $totalAllocated - $totalSpent;

        return [
            'total_allocated' => $totalAllocated,
            'total_spent' => $totalSpent,
            'remaining' => $remaining,
            'is_overspent' => $remaining < 0,
            'categories' => $categories,
        ];
    }
}
