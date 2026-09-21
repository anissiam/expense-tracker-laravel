<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BudgetClosing extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_id', 'remaining_amount', 'action', 'to_next_budget_amount', 
        'to_savings_amount', 'savings_id', 'performed_at'
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function savingAccount(): BelongsTo
    {
        return $this->belongsTo(Saving::class, 'savings_id');
    }
}