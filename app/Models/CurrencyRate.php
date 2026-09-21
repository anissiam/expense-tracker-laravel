<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrencyRate extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'rate', 'date'];

    protected $casts = [
        'rate' => 'decimal:8',
        'date' => 'date',
    ];
}