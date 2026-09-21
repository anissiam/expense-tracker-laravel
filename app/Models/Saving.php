<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Saving extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'current_balance', 'target_amount', 'target_date', 'icon', 'color'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SavingTransaction::class, 'savings_id');
    }

    public function budgetClosings(): HasMany
    {
        return $this->hasMany(BudgetClosing::class);
    }
}