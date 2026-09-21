<?php

namespace App\Http\Controllers;

use App\Models\IncomingIncome;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Carbon\Carbon;

class IncomingController extends Controller
{
    public function index(Request $request)
    {
        $incomings = IncomingIncome::where('user_id', $request->user()->id)
            ->orderBy('expected_date', 'desc')
            ->get();
        return response()->json($incomings);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'expected_date' => 'required|date',
            'account_id' => 'nullable|exists:payment_methods,id',
            'category' => 'nullable|string|max:100',
            'recurrence' => 'required|in:once,monthly,yearly',
            'notes' => 'nullable|string|max:500',
        ]);

        $incoming = IncomingIncome::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'amount' => $validated['amount'],
            'expected_date' => $validated['expected_date'],
            'account_id' => $validated['account_id'] ?? null,
            'category' => $validated['category'] ?? null,
            'recurrence' => $validated['recurrence'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json($incoming, 201);
    }

    public function update(Request $request, IncomingIncome $incoming)
    {
        if ($incoming->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'amount' => 'sometimes|numeric|min:0.01',
            'expected_date' => 'sometimes|date',
            'account_id' => 'nullable|exists:payment_methods,id',
            'category' => 'nullable|string|max:100',
            'recurrence' => 'sometimes|in:once,monthly,yearly',
            'notes' => 'nullable|string|max:500',
        ]);

        $incoming->update($validated);
        return response()->json($incoming);
    }

    public function destroy(IncomingIncome $incoming)
    {
        if ($incoming->user_id !== request()->user()->id) {
            abort(403);
        }
        $incoming->delete();
        return response()->json(null, 204);
    }

    public function markReceived(Request $request, IncomingIncome $incoming)
    {
        if ($incoming->user_id !== $request->user()->id) {
            abort(403);
        }

        $incoming->update([
            'status' => 'received',
            'received_date' => Carbon::today()->toDateString(),
        ]);

        if ($incoming->account_id) {
            PaymentMethod::where('id', $incoming->account_id)
                ->where('user_id', $request->user()->id)
                ->increment('balance', $incoming->amount);
        }

        return response()->json($incoming);
    }
}
