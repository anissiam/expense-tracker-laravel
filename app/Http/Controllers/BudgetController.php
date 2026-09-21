<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Services\BudgetAccessService;
use App\Services\BudgetService;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    protected BudgetService $budgetService;

    public function __construct(BudgetService $budgetService)
    {
        $this->budgetService = $budgetService;
    }

    public function index(Request $request)
    {
        $budgets = $this->budgetService->getUserBudgets($request->user()->id);
        return response()->json($budgets);
    }

    /**
     * Phase 1: active monthly budget with derived spent/remaining + overspend flags.
     * GET /api/budgets/active/summary?year=2026&month=9
     */
    public function active(Request $request)
    {
        $validated = $request->validate([
            'year' => 'nullable|integer|min:2000|max:2100',
            'month' => 'nullable|integer|between:1,12',
        ]);

        $summary = $this->budgetService->getActiveMonthlyBudget(
            $request->user()->id,
            $validated['year'] ?? null,
            $validated['month'] ?? null
        );

        return response()->json($summary);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2000',
            'mode' => 'required|in:salary,planned',
            'income' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:3',
        ]);

        try {
            $budget = $this->budgetService->createBudget($request->user()->id, $validated);
            return response()->json($budget, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function show(Request $request, Budget $budget)
    {
        if (!BudgetAccessService::canView($budget, $request->user()->id)) {
            abort(403);
        }

        $budget->setAttribute('my_role', BudgetAccessService::role($budget, $request->user()->id));

        return response()->json($budget);
    }

    public function update(Request $request, Budget $budget)
    {
        if (!BudgetAccessService::canEdit($budget, $request->user()->id)) {
            abort(403);
        }

        $validated = $request->validate([
            'mode' => 'sometimes|in:salary,planned',
            'income' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|max:3',
            'status' => 'sometimes|in:active,closed',
        ]);

        if (isset($validated['status']) && !BudgetAccessService::isOwner($budget, $request->user()->id)) {
            abort(403, 'Only the budget owner can close or change status.');
        }

        $map = [];
        if (isset($validated['mode'])) $map['type'] = $validated['mode'];
        if (isset($validated['income'])) $map['total_amount'] = $validated['income'];
        if (isset($validated['currency'])) $map['currency_code'] = $validated['currency'];
        if (isset($validated['status'])) $map['status'] = $validated['status'];

        $budget = $this->budgetService->updateBudget($budget, $map);
        return response()->json($budget);
    }

    public function destroy(Budget $budget)
    {
        if (!BudgetAccessService::isOwner($budget, request()->user()->id)) {
            abort(403, 'Only the budget owner can delete the budget.');
        }
        
        $budget->delete();
        return response()->json(null, 204);
    }
}
