<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'type', 'total_amount', 'currency_code', 'start_date', 'end_date', 'status', 'template_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(BudgetAllocation::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function closing(): HasOne
    {
        return $this->hasOne(BudgetClosing::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(MonthlyReview::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'template_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(BudgetMember::class);
    }

    public function acceptedMembers(): HasMany
    {
        return $this->hasMany(BudgetMember::class)->where('status', BudgetMember::STATUS_ACCEPTED);
    }

    public function isOwner(int $userId): bool
    {
        return (int) $this->user_id === $userId;
    }

    /**
     * Role of a user on this budget: 'owner' | 'editor' | 'viewer' | null.
     */
    public function userRole(int $userId): ?string
    {
        if ($this->isOwner($userId)) {
            return 'owner';
        }

        $member = $this->acceptedMembers()->where('user_id', $userId)->first()
            ?? BudgetMember::where('budget_id', $this->id)
                ->where('status', BudgetMember::STATUS_ACCEPTED)
                ->where('user_id', $userId)
                ->first();

        return $member?->role;
    }

    public function canView(int $userId): bool
    {
        return $this->userRole($userId) !== null;
    }

    public function canEdit(int $userId): bool
    {
        return in_array($this->userRole($userId), ['owner', 'editor'], true);
    }
}