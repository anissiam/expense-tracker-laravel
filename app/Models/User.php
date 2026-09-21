<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['supabase_id', 'name', 'email', 'password', 'currency_code', 'monthly_salary_day', 'timezone'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function settings(): HasOne
    {
        return $this->hasOne(UserSetting::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function incomings(): HasMany
    {
        return $this->hasMany(IncomingIncome::class);
    }

    public function savings(): HasMany
    {
        return $this->hasMany(Saving::class);
    }

    public function budgetTemplates(): HasMany
    {
        return $this->hasMany(Template::class);
    }

    public function budgetMemberships(): HasMany
    {
        return $this->hasMany(BudgetMember::class);
    }

    /**
     * Budgets shared with this user (accepted invites), excluding owned ones.
     */
    public function sharedBudgets(): BelongsToMany
    {
        return $this->belongsToMany(Budget::class, 'budget_members', 'user_id', 'budget_id')
            ->wherePivot('status', BudgetMember::STATUS_ACCEPTED)
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }
}
