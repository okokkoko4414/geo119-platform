<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'cache' => $this->checkCache(),
        ];

        $allHealthy = ! in_array(false, array_column($checks, 'healthy'), true);

        return response()->json([
            'status' => $allHealthy ? 'healthy' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $allHealthy ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            DB::select('SELECT 1');

            return ['healthy' => true, 'latency_ms' => 0];
        } catch (\Throwable $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }

    private function checkRedis(): array
    {
        try {
            Redis::ping();

            return ['healthy' => true];
        } catch (\Throwable $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        try {
            Cache::put('health_check', 'ok', 10);
            $value = Cache::get('health_check');

            return ['healthy' => $value === 'ok'];
        } catch (\Throwable $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }
}
