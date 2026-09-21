<?php

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;

class BudgetPolicy
{
    /**
     * Owner + accepted editors/viewers can view.
     */
    public function view(User $user, Budget $budget): bool
    {
        return $budget->canView($user->id);
    }

    /**
     * Owner + accepted editors can write (expenses, allocations, budget fields).
     */
    public function edit(User $user, Budget $budget): bool
    {
        return $budget->canEdit($user->id);
    }

    public function update(User $user, Budget $budget): bool
    {
        return $this->edit($user, $budget);
    }

    /**
     * Destructive / membership actions are owner-only.
     */
    public function manage(User $user, Budget $budget): bool
    {
        return $budget->isOwner($user->id);
    }

    public function delete(User $user, Budget $budget): bool
    {
        return $this->manage($user, $budget);
    }
}
