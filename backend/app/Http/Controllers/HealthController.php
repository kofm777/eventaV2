<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * Health check endpoint
     */
    public function health(): JsonResponse
    {
        try {
            // Test database connection
            DB::connection()->getPdo();
            $dbStatus = true;
        } catch (\Exception $e) {
            $dbStatus = false;
        }

        return response()->json([
            'status' => 'ok',
            'db' => $dbStatus,
            'timestamp' => now()->toISOString(),
        ]);
    }
}
