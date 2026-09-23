<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\BudgetAllocationController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\IncomingController;
use App\Http\Controllers\SavingController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\BudgetClosingController;
use App\Http\Controllers\BudgetInviteController;
use App\Http\Controllers\BudgetPartnerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SupabaseAuthController;
use App\Http\Controllers\VoiceController;
use App\Http\Controllers\HealthController;

// Public liveness probe — hit GET /api/health to check if the API is up.
// (Controller-based so `php artisan route:cache` keeps working on Render.)
Route::get('/health', HealthController::class);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
// Supabase Auth bridge: frontend signs in via Supabase, then syncs here
// to create the public.users row and get an API token.
Route::post('/auth/supabase/sync', [SupabaseAuthController::class, 'sync'])->middleware('throttle:30,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user/profile', [AuthController::class, 'profile']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);
    
    Route::get('budgets/active/summary', [BudgetController::class, 'active']);
    Route::apiResource('budgets', BudgetController::class);
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('expenses', ExpenseController::class);
    
    Route::get('budgets/{budget}/allocations', [BudgetAllocationController::class, 'index']);
    Route::post('budgets/{budget}/allocations', [BudgetAllocationController::class, 'store']);

    Route::get('dashboard/summary', [DashboardController::class, 'getSummary']);
    Route::get('reports/monthly', [ReportController::class, 'getMonthlyReport']);
    
    Route::apiResource('templates', TemplateController::class)->only(['index', 'store', 'show']);

    Route::post('budgets/{budget}/close', [BudgetClosingController::class, 'store']);
    Route::get('budgets/{budget}/closing', [BudgetClosingController::class, 'show']);
    Route::get('closings', [BudgetClosingController::class, 'index']);
    
    Route::apiResource('savings', SavingController::class);
    Route::get('savings/{saving}/transactions', [SavingController::class, 'transactions']);
    Route::post('savings/{saving}/transactions', [SavingController::class, 'addTransaction']);

    Route::post('voice/parse', [VoiceController::class, 'parse']);

    Route::apiResource('payment-methods', PaymentMethodController::class);

    Route::get('budgets/{budget}/partners', [BudgetPartnerController::class, 'index']);
    Route::post('budgets/{budget}/partners', [BudgetPartnerController::class, 'store'])->middleware('throttle:20,1');
    Route::patch('budgets/{budget}/partners/{member}', [BudgetPartnerController::class, 'update']);
    Route::delete('budgets/{budget}/partners/{member}', [BudgetPartnerController::class, 'destroy']);

    Route::get('invites/pending', [BudgetInviteController::class, 'pending']);
    Route::post('invites/{token}/accept', [BudgetInviteController::class, 'accept']);
    Route::post('invites/{token}/decline', [BudgetInviteController::class, 'decline']);

    Route::apiResource('incomings', IncomingController::class);
    Route::post('incomings/{incoming}/mark-received', [IncomingController::class, 'markReceived']);
});

// Public invite preview for the /invite/:token page. Must stay AFTER the
// auth group so GET /invites/pending is not captured as {token}="pending".
Route::get('/invites/{token}', [BudgetInviteController::class, 'show']);
