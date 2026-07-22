<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Optimization\BatchOptimizer;
use App\Services\Optimization\CircuitBreaker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BatchController
{
    public function __construct(
        private readonly BatchOptimizer $optimizer,
        private readonly CircuitBreaker $circuitBreaker,
    ) {}

    /**
     * POST /api/batch
     *
     * Submit a batch of texts for optimization.
     * Returns 202 Accepted for large batches that will be processed asynchronously.
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

        // Check circuit breaker before accepting batch
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

        // For large batches (50+ items), return 202 Accepted for async processing
        $statusCode = count($items) >= 50 ? 202 : 200;

        return response()->json([
            'job_id' => $estimate['job_id'],
            'estimated_cost_cents' => $estimate['estimated_cost'],
            'estimated_duration_seconds' => $estimate['estimated_duration'],
            'item_count' => count($items),
            'status' => $estimate['status'],
        ], $statusCode);
    }

    /**
     * GET /api/batch/{jobId}
     *
     * Poll for the status and results of an async batch job.
     */
    public function status(string $jobId): JsonResponse
    {
        // Results would be stored in Redis/cache keyed by job_id
        // For now, return a placeholder indicating job tracking is via Horizon
        return response()->json([
            'job_id' => $jobId,
            'status' => 'processing',
            'message' => 'Job status tracking via Laravel Horizon dashboard.',
        ]);
    }
}
