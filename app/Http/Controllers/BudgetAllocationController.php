<?php

namespace App\Http\Controllers;

use App\Models\BudgetAllocation;
use App\Models\Budget;
use App\Services\BudgetAccessService;
use App\Services\BudgetAllocationService;
use Illuminate\Http\Request;

class BudgetAllocationController extends Controller
{
    protected BudgetAllocationService $allocationService;

    public function __construct(BudgetAllocationService $allocationService)
    {
        $this->allocationService = $allocationService;
    }

    /**
     * Display a listing of the resource for a specific budget.
     */
    public function index(Request $request, Budget $budget)
    {
        if (!BudgetAccessService::canView($budget, $request->user()->id)) {
            abort(403);
        }
        
        $allocations = $this->allocationService->getBudgetAllocations($budget->id);
        return response()->json($allocations);
    }

    /**
     * Store or update an allocation.
     */
    public function store(Request $request, Budget $budget)
    {
        if (!BudgetAccessService::canEdit($budget, $request->user()->id)) {
            abort(403);
        }

        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'allocated_amount' => 'required|numeric|min:0',
        ]);

        $allocation = $this->allocationService->allocate($budget->id, $validated);

        return response()->json($allocation, 201);
    }
}
