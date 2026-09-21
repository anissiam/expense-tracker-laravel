<?php

namespace App\Services;

use App\Models\Saving;
use App\Models\SavingTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SavingService
{
    public function getUserSavings(int $userId): Collection
    {
        return Saving::where('user_id', $userId)->get();
    }

    public function createSaving(int $userId, array $data): Saving
    {
        return Saving::create([
            'user_id' => $userId,
            'name' => $data['name'],
            'current_balance' => $data['current_balance'] ?? 0,
            'target_amount' => $data['target_amount'] ?? null,
            'target_date' => $data['target_date'] ?? null,
            'icon' => $data['icon'] ?? null,
            'color' => $data['color'] ?? null,
        ]);
    }

    public function processTransaction(Saving $saving, array $data): SavingTransaction
    {
        return DB::transaction(function () use ($saving, $data) {
            $transaction = SavingTransaction::create([
                'savings_id' => $saving->id,
                'type' => $data['type'],
                'amount' => $data['amount'],
                'description' => $data['description'] ?? null,
                'related_budget_id' => $data['related_budget_id'] ?? null,
                'date' => $data['date'],
            ]);

            if (in_array($data['type'], ['add', 'interest'])) {
                $saving->increment('current_balance', $data['amount']);
            } elseif (in_array($data['type'], ['withdraw', 'transfer'])) {
                $saving->decrement('current_balance', $data['amount']);
            }

            return $transaction;
        });
    }
}
