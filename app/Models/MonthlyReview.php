<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MonthlyReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_id', 'total_budget', 'total_spent', 'total_saved', 
        'top_category_id', 'most_expensive_expense_id', 
        'average_daily_spending', 'review_data'
    ];

    protected $casts = [
        'review_data' => 'array',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function topCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'top_category_id');
    }

    public function mostExpensiveExpense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'most_expensive_expense_id');
    }
}