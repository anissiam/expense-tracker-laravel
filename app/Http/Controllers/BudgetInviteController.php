<?php

namespace App\Http\Controllers;

use App\Models\BudgetMember;
use Illuminate\Http\Request;

/**
 * Invitation lifecycle from the partner side:
 * preview (public) / pending (mine) / accept / decline.
 */
class BudgetInviteController extends Controller
{
    /**
     * Public preview for /invite/:token page (no auth required).
     * Returns non-sensitive info only.
     */
    public function show(string $token)
    {
        $member = BudgetMember::with(['budget:id,name,user_id', 'budget.user:id,name'])
            ->where('token', $token)
            ->firstOrFail();

        if ($member->isExpired()) {
            return response()->json(['message' => 'This invitation has expired. Ask the owner to re-invite you.', 'expired' => true], 410);
        }

        return response()->json([
            'email' => $member->email,
            'role' => $member->role,
            'status' => $member->status,
            'expires_at' => $member->expires_at,
            'budget_name' => $member->budget?->name,
            'budget_id' => $member->budget_id,
            'inviter_name' => $member->budget?->user?->name,
            'is_new_user' => $member->user_id === null,
        ]);
    }

    /**
     * Pending invitations for the logged-in user (matched by email).
     */
    public function pending(Request $request)
    {
        $email = strtolower($request->user()->email);

        $invites = BudgetMember::with(['budget:id,name,user_id', 'budget.user:id,name'])
            ->where('email', $email)
            ->where('status', BudgetMember::STATUS_PENDING)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (BudgetMember $m) => [
                'token' => $m->token,
                'budget_id' => $m->budget_id,
                'budget_name' => $m->budget?->name,
                'inviter_name' => $m->budget?->user?->name,
                'role' => $m->role,
                'expires_at' => $m->expires_at,
            ]);

        return response()->json($invites);
    }

    public function accept(Request $request, string $token)
    {
        $member = BudgetMember::where('token', $token)->firstOrFail();
        $user = $request->user();

        if ($member->status === BudgetMember::STATUS_ACCEPTED) {
            return response()->json(['message' => 'Invitation already accepted.'], 200);
        }

        if ($member->isExpired()) {
            return response()->json(['message' => 'This invitation has expired.'], 410);
        }

        // Token alone is not enough: the logged-in email must match the invite.
        if (strtolower($user->email) !== strtolower($member->email)) {
            abort(403, 'This invitation was sent to a different email address.');
        }

        $member->update([
            'user_id' => $user->id,
            'status' => BudgetMember::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);

        return response()->json([
            'message' => 'Invitation accepted.',
            'budget_id' => $member->budget_id,
            'role' => $member->role,
        ]);
    }

    public function decline(Request $request, string $token)
    {
        $member = BudgetMember::where('token', $token)->firstOrFail();
        $user = $request->user();

        if (strtolower($user->email) !== strtolower($member->email)) {
            abort(403, 'This invitation was sent to a different email address.');
        }

        $member->update(['status' => BudgetMember::STATUS_DECLINED]);

        return response()->json(['message' => 'Invitation declined.']);
    }

    /**
     * Called right after registration when ?invite=token was present.
     * Links freshly created accounts whose email matches a pending invite.
     */
    public static function acceptForNewUser(int $userId, string $email, ?string $token = null): int
    {
        $query = BudgetMember::where('email', strtolower($email))
            ->where('status', BudgetMember::STATUS_PENDING)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });

        if ($token) {
            $query->where('token', $token);
        }

        $count = 0;
        foreach ($query->get() as $member) {
            $member->update([
                'user_id' => $userId,
                'status' => BudgetMember::STATUS_ACCEPTED,
                'accepted_at' => now(),
            ]);
            $count++;
        }

        return $count;
    }
}
