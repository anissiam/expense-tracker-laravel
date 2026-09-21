<?php

namespace App\Http\Controllers;

use App\Mail\PartnerInviteMail;
use App\Models\Budget;
use App\Models\BudgetMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Owner-only management of budget partners:
 * list / invite (email+role) / change role / remove.
 */
class BudgetPartnerController extends Controller
{
    public function index(Request $request, Budget $budget)
    {
        if ((int) $budget->user_id !== $request->user()->id) {
            // Partners may see the member list (read), but only owner manages.
            if (! $budget->canView($request->user()->id)) {
                abort(403);
            }
        }

        $members = BudgetMember::with(['user:id,name,email'])
            ->where('budget_id', $budget->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn (BudgetMember $m) => [
                'id' => $m->id,
                'budget_id' => $m->budget_id,
                'email' => $m->email,
                'name' => $m->user?->name,
                'user_id' => $m->user_id,
                'role' => $m->role,
                'status' => $m->status,
                'expires_at' => $m->expires_at,
                'accepted_at' => $m->accepted_at,
                'created_at' => $m->created_at,
            ]);

        return response()->json([
            'owner' => ['id' => $budget->user_id, 'name' => $budget->user?->name, 'email' => $budget->user?->email],
            'members' => $members,
        ]);
    }

    public function store(Request $request, Budget $budget)
    {
        if ((int) $budget->user_id !== $request->user()->id) {
            abort(403, 'Only the budget owner can invite partners.');
        }

        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'role' => 'required|in:editor,viewer',
        ]);

        $email = strtolower(trim($validated['email']));

        if ($email === strtolower($request->user()->email)) {
            return response()->json(['message' => 'You cannot invite yourself.'], 422);
        }

        if ($email === strtolower($budget->user?->email ?? '')) {
            return response()->json(['message' => 'This user already owns the budget.'], 422);
        }

        $existing = BudgetMember::where('budget_id', $budget->id)
            ->where('email', $email)
            ->first();

        if ($existing && in_array($existing->status, [BudgetMember::STATUS_ACCEPTED, BudgetMember::STATUS_PENDING], true)) {
            // Refresh expired pending invites instead of erroring.
            if ($existing->status === BudgetMember::STATUS_PENDING && $existing->isExpired()) {
                $existing->update([
                    'role' => $validated['role'],
                    'token' => Str::random(48),
                    'expires_at' => now()->addDays(7),
                    'invited_by' => $request->user()->id,
                ]);
                $fresh = $existing->fresh();
                $this->sendInvite($budget, $fresh, $request->user());
                return response()->json($this->serialize($fresh, $this->inviteUrl($fresh)), 200);
            }
            return response()->json(['message' => 'This email is already a partner or has a pending invitation.'], 422);
        }

        $partnerUser = User::where('email', $email)->first();

        // Allow re-invite after decline: drop the old row first (unique on budget+email).
        if ($existing && in_array($existing->status, [BudgetMember::STATUS_DECLINED, BudgetMember::STATUS_REVOKED], true)) {
            $existing->delete();
        }

        $member = BudgetMember::create([
            'budget_id' => $budget->id,
            'user_id' => $partnerUser?->id,
            'email' => $email,
            'role' => $validated['role'],
            'status' => BudgetMember::STATUS_PENDING,
            'token' => Str::random(48),
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->sendInvite($budget, $member, $request->user());

        return response()->json($this->serialize($member, $this->inviteUrl($member)), 201);
    }

    public function update(Request $request, Budget $budget, BudgetMember $member)
    {
        if ((int) $budget->user_id !== $request->user()->id) {
            abort(403, 'Only the budget owner can change roles.');
        }
        if ((int) $member->budget_id !== (int) $budget->id) {
            abort(404);
        }

        $validated = $request->validate([
            'role' => 'required|in:editor,viewer',
        ]);

        $member->update(['role' => $validated['role']]);

        return response()->json($this->serialize($member->fresh()));
    }

    public function destroy(Request $request, Budget $budget, BudgetMember $member)
    {
        if ((int) $budget->user_id !== $request->user()->id) {
            abort(403, 'Only the budget owner can remove partners.');
        }
        if ((int) $member->budget_id !== (int) $budget->id) {
            abort(404);
        }

        // Deleting anytime: pending invites are cancelled, accepted are revoked.
        $member->delete();

        return response()->json(null, 204);
    }

    protected function sendInvite(Budget $budget, BudgetMember $member, User $inviter): void
    {
        $acceptUrl = $this->inviteUrl($member);

        // Always log the link so owners can share it manually when no real
        // SMTP mailer is configured (e.g. MAIL_MAILER=log) or no queue
        // worker is running (mail is queued via ShouldQueue).
        Log::info('Partner invite created', [
            'budget_id' => $budget->id,
            'email' => $member->email,
            'role' => $member->role,
            'invite_url' => $acceptUrl,
        ]);

        try {
            Mail::to($member->email)->send(new PartnerInviteMail(
                budget: $budget,
                member: $member,
                inviterName: $inviter->name,
                isNewUser: $member->user_id === null,
                acceptUrl: $acceptUrl,
            ));
        } catch (\Throwable $e) {
            report($e);
            // Invitation is still stored; mail failure must not roll back the invite.
        }
    }

    protected function inviteUrl(BudgetMember $member): string
    {
        $frontend = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');

        return "{$frontend}/invite/{$member->token}";
    }

    protected function serialize(BudgetMember $member, ?string $inviteUrl = null): array
    {
        $member->loadMissing('user:id,name,email');
        return [
            'id' => $member->id,
            'budget_id' => $member->budget_id,
            'email' => $member->email,
            'name' => $member->user?->name,
            'user_id' => $member->user_id,
            'role' => $member->role,
            'status' => $member->status,
            'expires_at' => $member->expires_at,
            'accepted_at' => $member->accepted_at,
            'created_at' => $member->created_at,
            // Returned only on invite creation so the owner can copy/share
            // the link manually when email delivery isn't configured.
            'invite_url' => $inviteUrl ?? $this->inviteUrl($member),
        ];
    }
}
