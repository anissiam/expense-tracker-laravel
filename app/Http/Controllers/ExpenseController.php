<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Services\BudgetAccessService;
use App\Services\ExpenseService;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    protected ExpenseService $expenseService;

    public function __construct(ExpenseService $expenseService)
    {
        $this->expenseService = $expenseService;
    }

    public function index(Request $request)
    {
        $filters = $request->only(['budget_id', 'category_id']);
        $expenses = $this->expenseService->getUserExpenses($request->user()->id, $filters);
        return response()->json($expenses);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'subcategory_id' => 'nullable|exists:categories,id',
            'budget_id' => 'nullable|exists:budgets,id',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|max:10',
            'date' => 'required|date',
            'description' => 'nullable|string|max:500',
            'payment_method' => 'nullable|string|max:50',
            'source' => 'nullable|in:manual,voice',
        ]);

        $validated['currency_code'] = $validated['currency'] ?? 'USD';
        unset($validated['currency']);

        if (!empty($validated['budget_id'])) {
            $budget = \App\Models\Budget::find($validated['budget_id']);
            if (!$budget || !BudgetAccessService::canEdit($budget, $request->user()->id)) {
                abort(403, 'You do not have edit access to this budget.');
            }
        }

        $expense = $this->expenseService->createExpense($request->user()->id, $validated);

        return response()->json($expense, 201);
    }

    public function show(Request $request, Expense $expense)
    {
        if (!$this->canViewExpense($request, $expense)) {
            abort(403);
        }

        return response()->json($expense->load(['category', 'subcategory', 'budget']));
    }

    public function update(Request $request, Expense $expense)
    {
        if (!$this->canEditExpense($request, $expense)) {
            abort(403);
        }

        $validated = $request->validate([
            'category_id' => 'sometimes|exists:categories,id',
            'subcategory_id' => 'nullable|exists:categories,id',
            'budget_id' => 'nullable|exists:budgets,id',
            'amount' => 'sometimes|numeric|min:0.01',
            'currency' => 'nullable|string|max:10',
            'date' => 'sometimes|date',
            'description' => 'nullable|string|max:500',
            'payment_method' => 'nullable|string|max:50',
            'source' => 'nullable|in:manual,voice',
        ]);

        if (isset($validated['currency'])) {
            $validated['currency_code'] = $validated['currency'];
            unset($validated['currency']);
        }

        if (!empty($validated['budget_id'])) {
            $target = \App\Models\Budget::find($validated['budget_id']);
            if (!$target || !BudgetAccessService::canEdit($target, $request->user()->id)) {
                abort(403, 'You cannot move this expense to that budget.');
            }
        }

        $expense = $this->expenseService->updateExpense($expense, $validated);
        return response()->json($expense);
    }

    public function destroy(Request $request, Expense $expense)
    {
        if (!$this->canEditExpense($request, $expense)) {
            abort(403);
        }

        $this->expenseService->deleteExpense($expense);
        return response()->json(null, 204);
    }

    /**
     * Owner always has access. Partners inherit access from the linked budget:
     * viewers can read, editors can read+write. Personal (budget-less)
     * expenses stay private to their creator.
     */
    protected function canViewExpense(Request $request, Expense $expense): bool
    {
        if ((int) $expense->user_id === (int) $request->user()->id) {
            return true;
        }
        if ($expense->budget_id && $expense->budget) {
            return BudgetAccessService::canView($expense->budget, $request->user()->id);
        }
        if ($expense->budget_id) {
            $budget = \App\Models\Budget::find($expense->budget_id);
            return $budget && BudgetAccessService::canView($budget, $request->user()->id);
        }
        return false;
    }

    protected function canEditExpense(Request $request, Expense $expense): bool
    {
        if ((int) $expense->user_id === (int) $request->user()->id) {
            // Own expense on a shared budget: still requires edit rights
            // (a downgraded viewer must not keep writing).
            if ($expense->budget_id) {
                $budget = $expense->budget ?? \App\Models\Budget::find($expense->budget_id);
                if ($budget && (int) $budget->user_id !== (int) $request->user()->id) {
                    return BudgetAccessService::canEdit($budget, $request->user()->id);
                }
            }
            return true;
        }
        if ($expense->budget_id) {
            $budget = $expense->budget ?? \App\Models\Budget::find($expense->budget_id);
            return $budget && BudgetAccessService::canEdit($budget, $request->user()->id);
        }
        return false;
    }
}
