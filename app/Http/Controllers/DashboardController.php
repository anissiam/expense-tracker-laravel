<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Services\BudgetAccessService;
use App\Services\BudgetService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Get dashboard summary for a specific budget.
     * Phase 1: remaining = allocated - spent, overspend flagged, never mutates plan.
     */
    public function getSummary(Request $request, BudgetService $budgets)
    {
        $request->validate([
            'budget_id' => 'required|exists:budgets,id'
        ]);

        $budget = Budget::findOrFail($request->budget_id);

        if (!BudgetAccessService::canView($budget, $request->user()->id)) {
            abort(403);
        }

        $calc = $budgets->recalculateBudgetValues($budget->id);

        $allocations = \App\Models\BudgetAllocation::where('budget_id', $budget->id)
            ->with('category')
            ->get();

        // Recent expenses
        $recentExpenses = \App\Models\Expense::where('budget_id', $budget->id)
            ->with(['category', 'subcategory'])
            ->orderBy('date', 'desc')
            ->take(5)
            ->get();

        return response()->json([
            'budget' => $budget,
            'summary' => [
                'total_allocated' => $calc['total_allocated'],
                'total_spent' => $calc['total_spent'],
                'remaining' => $calc['remaining'],
                'is_overspent' => $calc['is_overspent'],
                'spending_progress' => $calc['total_allocated'] > 0 ? round(($calc['total_spent'] / $calc['total_allocated']) * 100, 2) : 0,
            ],
            'category_spending' => collect($calc['categories'])->map(function ($row) {
                return [
                    'category_id' => $row['category_id'],
                    'category' => $row['category_name'],
                    'allocated' => $row['allocated'],
                    'spent' => $row['spent'],
                    'remaining' => $row['remaining'],
                    'is_overspent' => $row['is_overspent'],
                    'progress' => $row['allocated'] > 0 ? round(($row['spent'] / $row['allocated']) * 100, 2) : ($row['spent'] > 0 ? 100 : 0),
                ];
            }),
            'recent_expenses' => $recentExpenses,
        ]);
    }
}
