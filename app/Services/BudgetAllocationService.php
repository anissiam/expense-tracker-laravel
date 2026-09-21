<?php

namespace App\Services;

use App\Models\BudgetAllocation;
use Illuminate\Support\Collection;

class BudgetAllocationService
{
    /**
     * Get allocations for a specific budget.
     */
    public function getBudgetAllocations(int $budgetId): Collection
    {
        return BudgetAllocation::where('budget_id', $budgetId)->get();
    }

    /**
     * Allocate budget to a category.
     */
    public function allocate(int $budgetId, array $data): BudgetAllocation
    {
        return BudgetAllocation::updateOrCreate(
            [
                'budget_id' => $budgetId,
                'category_id' => $data['category_id'],
            ],
            [
                'allocated_amount' => $data['allocated_amount'],
            ]
        );
    }
}
