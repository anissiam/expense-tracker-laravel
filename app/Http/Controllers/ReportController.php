<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Expense;
use App\Services\BudgetAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Generate a spending report.
     */
    public function getMonthlyReport(Request $request)
    {
        $request->validate([
            'year' => 'required|integer',
            'month' => 'required|integer',
        ]);

        $visibleIds = BudgetAccessService::visibleBudgetIds($request->user()->id);
        $budget = Budget::whereIn('id', $visibleIds)
            ->whereYear('start_date', $request->year)
            ->whereMonth('start_date', $request->month)
            ->first();

        if (!$budget) {
            return response()->json(['error' => 'Budget not found for the specified month/year.'], 404);
        }

        $expensesByCategory = Expense::where('budget_id', $budget->id)
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->groupBy('category_id')
            ->with('category')
            ->get();

        $expensesByDay = Expense::where('budget_id', $budget->id)
            ->select('date', DB::raw('SUM(amount) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'budget_id' => $budget->id,
            'period' => $request->year . '-' . str_pad($request->month, 2, '0', STR_PAD_LEFT),
            'by_category' => $expensesByCategory,
            'by_day' => $expensesByDay,
        ]);
    }
}
