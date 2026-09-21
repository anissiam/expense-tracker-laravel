<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\BudgetMember;

/**
 * Central helper for budget sharing checks so controllers stay thin.
 * Viewer = read, Editor = read+write, Owner = everything + manage partners.
 */
class BudgetAccessService
{
    public static function role(Budget $budget, int $userId): ?string
    {
        if ((int) $budget->user_id === $userId) {
            return 'owner';
        }

        return BudgetMember::where('budget_id', $budget->id)
            ->where('user_id', $userId)
            ->where('status', BudgetMember::STATUS_ACCEPTED)
            ->value('role');
    }

    public static function canView(Budget $budget, int $userId): bool
    {
        return self::role($budget, $userId) !== null;
    }

    public static function canEdit(Budget $budget, int $userId): bool
    {
        return in_array(self::role($budget, $userId), ['owner', 'editor'], true);
    }

    public static function isOwner(Budget $budget, int $userId): bool
    {
        return (int) $budget->user_id === $userId;
    }

    /**
     * All budget ids visible to the user (owned + accepted shares).
     *
     * @return int[]
     */
    public static function visibleBudgetIds(int $userId): array
    {
        $owned = Budget::where('user_id', $userId)->pluck('id')->all();
        $shared = BudgetMember::where('user_id', $userId)
            ->where('status', BudgetMember::STATUS_ACCEPTED)
            ->pluck('budget_id')
            ->all();

        return array_values(array_unique(array_map('intval', array_merge($owned, $shared))));
    }
}
