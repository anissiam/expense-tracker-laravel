<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavingTransaction extends Model
{
    use HasFactory;

    protected $fillable = ['savings_id', 'type', 'amount', 'description', 'related_budget_id', 'date'];

    public function savingAccount(): BelongsTo
    {
        return $this->belongsTo(Saving::class, 'savings_id');
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class, 'related_budget_id');
    }
}