<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\BudgetClosing;
use App\Models\Saving;
use App\Models\SavingTransaction;
use Illuminate\Support\Facades\DB;

class BudgetClosingService
{
    /**
     * Close a budget and handle the remaining balance.
     */
    public function closeBudget(int $userId, int $budgetId, array $data): BudgetClosing
    {
        $budget = Budget::where('user_id', $userId)->where('id', $budgetId)->firstOrFail();
        
        if ($budget->status === 'closed') {
            throw new \Exception("Budget is already closed.");
        }

        $remainingAmount = $data['remaining_amount'];
        $action = $data['action']; // carry_forward, to_savings, split, ignore

        return DB::transaction(function () use ($budget, $remainingAmount, $action, $data) {
            
            $closing = BudgetClosing::create([
                'budget_id' => $budget->id,
                'remaining_amount' => $remainingAmount,
                'action' => $action,
                'to_next_budget_amount' => $data['to_next_budget_amount'] ?? 0,
                'to_savings_amount' => $data['to_savings_amount'] ?? 0,
                'savings_id' => $data['savings_id'] ?? null,
                'performed_at' => now(),
            ]);

            if ($action === 'to_savings' || $action === 'split') {
                $savingsAmount = $data['to_savings_amount'] ?? $remainingAmount;
                if ($savingsAmount > 0 && isset($data['savings_id'])) {
                    $saving = Saving::findOrFail($data['savings_id']);
                    $saving->increment('current_balance', $savingsAmount);
                    
                    SavingTransaction::create([
                        'savings_id' => $saving->id,
                        'type' => 'add',
                        'amount' => $savingsAmount,
                        'description' => 'Transfer from closed budget: ' . $budget->name,
                        'related_budget_id' => $budget->id,
                        'date' => now()->toDateString(),
                    ]);
                }
            }

            $budget->update(['status' => 'closed']);

            return $closing;
        });
    }
}
