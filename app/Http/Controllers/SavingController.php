<?php

namespace App\Http\Controllers;

use App\Models\Saving;
use App\Services\SavingService;
use Illuminate\Http\Request;

class SavingController extends Controller
{
    protected SavingService $savingService;

    public function __construct(SavingService $savingService)
    {
        $this->savingService = $savingService;
    }

    public function index(Request $request)
    {
        $savings = $this->savingService->getUserSavings($request->user()->id);
        return response()->json($savings);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'current_balance' => 'nullable|numeric|min:0',
            'target_amount' => 'nullable|numeric|min:0',
            'target_date' => 'nullable|date',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:50',
        ]);

        $saving = $this->savingService->createSaving($request->user()->id, $validated);
        return response()->json($saving, 201);
    }

    public function update(Request $request, Saving $saving)
    {
        if ($saving->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'current_balance' => 'sometimes|numeric|min:0',
            'target_amount' => 'nullable|numeric|min:0',
            'target_date' => 'nullable|date',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:50',
        ]);

        $saving->update($validated);
        return response()->json($saving);
    }

    public function destroy(Request $request, Saving $saving)
    {
        if ($saving->user_id !== $request->user()->id) {
            abort(403);
        }
        $saving->delete();
        return response()->json(null, 204);
    }

    public function transactions(Request $request, Saving $saving)
    {
        if ($saving->user_id !== $request->user()->id) {
            abort(403);
        }
        $txs = $saving->transactions()->orderBy('date', 'desc')->get();
        return response()->json($txs);
    }

    public function addTransaction(Request $request, Saving $saving)
    {
        if ($saving->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'type' => 'required|in:add,withdraw,transfer,interest',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'related_budget_id' => 'nullable|exists:budgets,id',
            'date' => 'required|date',
        ]);

        $transaction = $this->savingService->processTransaction($saving, $validated);
        return response()->json($transaction, 201);
    }
}
