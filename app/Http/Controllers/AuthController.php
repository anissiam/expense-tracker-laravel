<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'currency' => 'nullable|string|max:3',
            'invite_token' => 'nullable|string',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'currency_code' => $request->currency ?? 'USD',
        ]);

        // Partner flow: if the new account matches pending invitation(s),
        // accept them automatically (signup-via-invite requirement).
        $acceptedInvites = BudgetInviteController::acceptForNewUser(
            $user->id,
            $user->email,
            $request->input('invite_token')
        );

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('auth_token')->plainTextToken,
            'accepted_invites' => $acceptedInvites,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('auth_token')->plainTextToken,
        ]);
    }

    public function profile(Request $request)
    {
        return response()->json($request->user());
    }

    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'currency' => 'sometimes|string|max:3',
            'monthly_salary_day' => 'nullable|integer|between:1,31',
        ]);

        $user = $request->user();

        $map = [];
        if (isset($validated['name'])) $map['name'] = $validated['name'];
        if (isset($validated['currency'])) $map['currency_code'] = $validated['currency'];
        if (array_key_exists('monthly_salary_day', $validated)) $map['monthly_salary_day'] = $validated['monthly_salary_day'];

        $user->update($map);

        return response()->json($user);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
}
