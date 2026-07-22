<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\BatchOptimizeJob;
use App\Services\Contracts\RedisStore;
use App\Services\Optimization\BatchOptimizer;
use App\Services\Optimization\CircuitBreaker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BatchController
{
    public function __construct(
        private readonly BatchOptimizer $optimizer,
        private readonly CircuitBreaker $circuitBreaker,
        private readonly RedisStore $redis,
    ) {}

    /**
     * POST /api/v1/batch/optimize
     *
     * Submit a batch of texts for optimization.
     * Small batches (<50 items) process synchronously.
     * Large batches (50+ items) dispatch to Horizon and return 202.
     * Returns 503 if circuit breaker is open.
     */
    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1|max:250',
            'items.*.source_text' => 'required|string|max:25000',
            'items.*.target_locale' => 'required|string|size:5',
            'items.*.optimization_type' => 'required|string|in:grammar,clarity,tone,conciseness,fluency,full',
        ]);

        if (! $this->circuitBreaker->isAvailable()) {
            return response()->json([
                'error' => 'Service temporarily unavailable',
                'message' => 'Circuit breaker is open. DeepSeek is experiencing failures.',
                'retry_after_seconds' => $this->circuitBreaker->retryAfterSeconds(),
            ], 503)->withHeaders([
                'Retry-After' => (string) $this->circuitBreaker->retryAfterSeconds(),
            ]);
        }

        $items = $validated['items'];
        $estimate = $this->optimizer->submit($items);
        $jobId = $estimate['job_id'];

        if (count($items) >= 50) {
            BatchOptimizeJob::dispatch($jobId, $items)->onQueue('optimizations');

            return response()->json([
                'job_id' => $jobId,
                'estimated_cost_cents' => $estimate['estimated_cost'],
                'estimated_duration_seconds' => $estimate['estimated_duration'],
                'item_count' => count($items),
                'status' => 'accepted',
                'poll_url' => "/api/v1/batch/{$jobId}",
            ], 202);
        }

        $result = $this->optimizer->process($items);

        return response()->json([
            'job_id' => $jobId,
            'item_count' => count($items),
            'status' => 'completed',
            'summary' => $result['summary'],
            'details' => $result['details'],
        ]);
    }

    /**
     * GET /api/v1/batch/{jobId}
     *
     * Poll for the status and results of an async batch job.
     */
    public function status(string $jobId): JsonResponse
    {
        $statusJson = $this->redis->get("batch:job:{$jobId}:status");

        if (! $statusJson) {
            return response()->json([
                'job_id' => $jobId,
                'status' => 'unknown',
                'message' => 'Job not found. It may have expired or never existed.',
            ], 404);
        }

        $status = json_decode($statusJson, true);

        $response = [
            'job_id' => $jobId,
            'status' => $status['status'] ?? 'unknown',
        ];

        if (($status['status'] ?? '') === 'completed') {
            $resultJson = $this->redis->get("batch:job:{$jobId}:result");
            if ($resultJson) {
                $result = json_decode($resultJson, true);
                $response['summary'] = $result['summary'] ?? null;
                $response['meta'] = $result['meta'] ?? null;
            }
        }

        if (($status['status'] ?? '') === 'failed') {
            $response['error'] = $status['error'] ?? 'Unknown error';
        }

        return response()->json($response);
    }
}
