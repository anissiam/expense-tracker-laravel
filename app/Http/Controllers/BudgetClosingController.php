<?php

namespace App\Http\Controllers;

use App\Models\BudgetClosing;
use App\Models\Budget;
use App\Services\BudgetAccessService;
use App\Services\BudgetClosingService;
use Illuminate\Http\Request;

class BudgetClosingController extends Controller
{
    protected BudgetClosingService $closingService;

    public function __construct(BudgetClosingService $closingService)
    {
        $this->closingService = $closingService;
    }

    public function store(Request $request, $budgetId)
    {
        $validated = $request->validate([
            'remaining_amount' => 'required|numeric',
            'action' => 'required|in:carry_forward,to_savings,split,ignore',
            'to_next_budget_amount' => 'nullable|numeric|min:0',
            'to_savings_amount' => 'nullable|numeric|min:0',
            'savings_id' => 'nullable|exists:savings,id',
        ]);

        $budget = Budget::findOrFail($budgetId);
        if (!BudgetAccessService::isOwner($budget, $request->user()->id)) {
            abort(403, 'Only the budget owner can close the budget.');
        }

        try {
            $closing = $this->closingService->closeBudget($request->user()->id, $budgetId, $validated);
            return response()->json($closing, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function show(Request $request, $budgetId)
    {
        $budget = Budget::findOrFail($budgetId);
        if (!BudgetAccessService::canView($budget, $request->user()->id)) {
            abort(403);
        }

        $closing = BudgetClosing::where('budget_id', $budgetId)
            ->first();

        if (!$closing) {
            return response()->json(null);
        }

        return response()->json($closing);
    }

    public function index(Request $request)
    {
        $visibleIds = BudgetAccessService::visibleBudgetIds($request->user()->id);
        $closings = BudgetClosing::whereIn('budget_id', $visibleIds)->orderBy('performed_at', 'desc')->get();

        return response()->json($closings);
    }
}
