<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Bridge between Supabase Auth (source of truth for credentials)
 * and the app's public.users record (lives in the same Supabase Postgres).
 *
 * Flow: frontend signs up / signs in via Supabase Auth (GoTrue), then calls
 * this endpoint with the Supabase access token. We validate the token
 * against Supabase (GET /auth/v1/user), upsert the public.users row
 * (matched by supabase_id, then email), auto-accept pending budget
 * invites, and return a Sanctum token so all existing /api/* endpoints
 * keep working unchanged.
 */
class SupabaseAuthController extends Controller
{
    public function sync(Request $request)
    {
        $validated = $request->validate([
            'access_token' => 'required|string',
            'name' => 'nullable|string|max:255',
            'currency' => 'nullable|string|max:3',
            'invite_token' => 'nullable|string',
        ]);

        $supabaseUrl = rtrim((string) config('services.supabase.url', ''), '/');
        $anonKey = (string) config('services.supabase.anon_key', '');

        if ($supabaseUrl === '' || $anonKey === '') {
            return response()->json(['message' => 'Supabase is not configured on the server.'], 500);
        }

        $supabaseUser = $this->fetchSupabaseUser($supabaseUrl, $anonKey, $validated['access_token']);

        if ($supabaseUser === null) {
            return response()->json(['message' => 'Invalid or expired Supabase session. Please sign in again.'], 401);
        }

        $supabaseId = $supabaseUser['id'] ?? null;
        $email = strtolower(trim((string) ($supabaseUser['email'] ?? '')));

        if (! $supabaseId || $email === '') {
            return response()->json(['message' => 'Supabase account has no usable id/email.'], 422);
        }

        $meta = is_array($supabaseUser['user_metadata'] ?? null) ? $supabaseUser['user_metadata'] : [];
        $name = $validated['name'] ?? $meta['name'] ?? explode('@', $email)[0];
        $currency = strtoupper((string) ($validated['currency'] ?? $meta['currency'] ?? 'USD'));

        $user = User::where('supabase_id', $supabaseId)->first();
        $justCreated = false;

        if (! $user) {
            $user = User::where('email', $email)->first();
        }

        if (! $user) {
            $user = User::create([
                'supabase_id' => $supabaseId,
                'name' => $name,
                'email' => $email,
                // Passwords are managed by Supabase Auth; keep a random
                // hash so the legacy non-null column stays satisfied.
                'password' => Hash::make(Str::random(40)),
                'currency_code' => $currency,
                'email_verified_at' => now(),
            ]);
            $justCreated = true;
        } else {
            $updates = ['supabase_id' => $supabaseId];
            // Don't clobber an existing app display name with metadata,
            // unless the caller explicitly passed a name (signup form).
            if (! empty($validated['name'])) {
                $updates['name'] = $validated['name'];
            }
            $user->update($updates);
        }

        // Partner flow: auto-accept pending invitation(s) for new accounts
        // (or when an explicit invite_token was passed, e.g. /invite/:token).
        $acceptedInvites = 0;
        if ($justCreated || ! empty($validated['invite_token'])) {
            $acceptedInvites = BudgetInviteController::acceptForNewUser(
                $user->id,
                $user->email,
                $validated['invite_token'] ?? null
            );
        }

        return response()->json([
            'user' => $user->fresh(),
            'token' => $user->createToken('auth_token')->plainTextToken,
            'accepted_invites' => $acceptedInvites,
        ], $justCreated ? 201 : 200);
    }

    /**
     * Validate a Supabase access token by asking GoTrue for its user.
     * Returns the user array on success, null on any failure.
     */
    protected function fetchSupabaseUser(string $supabaseUrl, string $anonKey, string $accessToken): ?array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['apikey' => $anonKey])
                ->withToken($accessToken)
                ->get($supabaseUrl.'/auth/v1/user');

            if ($response->failed()) {
                return null;
            }

            $data = $response->json();

            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
