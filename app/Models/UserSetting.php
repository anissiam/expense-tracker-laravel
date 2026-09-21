<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserSetting extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'date_format', 'time_format', 'default_view', 'notifications_enabled'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}