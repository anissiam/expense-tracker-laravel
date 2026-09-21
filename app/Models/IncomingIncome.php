<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncomingIncome extends Model
{
    use HasFactory;

    protected $table = 'incomings';

    protected $fillable = [
        'user_id', 'title', 'amount', 'expected_date', 'account_id', 'category',
        'recurrence', 'status', 'received_date', 'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'recurrence' => 'string',
        'status' => 'string',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'account_id');
    }
}