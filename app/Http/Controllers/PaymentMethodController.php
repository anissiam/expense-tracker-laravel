<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function index(Request $request)
    {
        $methods = PaymentMethod::where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        return response()->json($methods);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:cash,card,bank,ewallet,wallet,crypto,other',
            'balance' => 'nullable|numeric|min:0',
            'account_number' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
            'icon' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',
        ]);

        $method = PaymentMethod::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'balance' => $validated['balance'] ?? 0,
            'account_number' => $validated['account_number'] ?? null,
            'color' => $validated['color'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json($method, 201);
    }

    public function show(PaymentMethod $paymentMethod)
    {
        if ($paymentMethod->user_id !== request()->user()->id) {
            abort(403);
        }
        return response()->json($paymentMethod);
    }

    public function update(Request $request, PaymentMethod $paymentMethod)
    {
        if ($paymentMethod->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:cash,card,bank,ewallet,wallet,crypto,other',
            'balance' => 'sometimes|numeric|min:0',
            'account_number' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
            'icon' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',
        ]);

        $paymentMethod->update($validated);
        return response()->json($paymentMethod);
    }

    public function destroy(PaymentMethod $paymentMethod)
    {
        if ($paymentMethod->user_id !== request()->user()->id) {
            abort(403);
        }
        $paymentMethod->update(['is_active' => false]);
        return response()->json(null, 204);
    }
}
