<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * Public liveness probe: GET /api/health
     * Verifies the API is running and reports DB connectivity.
     * Always returns 200 so uptime monitors stay green; check the
     * `db` field for database status.
     */
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('SELECT 1');
            $db = 'ok';
        } catch (\Throwable $e) {
            $db = 'fail: '.$e->getMessage();
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'API is running',
            'app' => config('app.name'),
            'env' => config('app.env'),
            'time' => now()->toIso8601String(),
            'db' => $db,
        ]);
    }
}
