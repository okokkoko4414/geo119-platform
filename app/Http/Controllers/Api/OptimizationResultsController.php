<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OptimizationResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OptimizationResultsController extends Controller
{
    /**
     * GET /api/v1/optimizations/recent
     *
     * Returns the most recent optimization results with before/after scores.
     */
    public function recent(Request $request): JsonResponse
    {
        $limit = min((int) $request->query('limit', '20'), 100);

        $results = OptimizationResult::query()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (OptimizationResult $r): array => [
                'id' => $r->id,
                'target_locale' => $r->target_locale,
                'optimization_type' => $r->optimization_type,
                'before_score' => $r->before_score,
                'after_score' => $r->after_score,
                'improvement' => $r->improvement,
                'improvement_pct' => round($r->improvement * 100, 1),
                'cost_cents' => $r->cost_cents,
                'input_tokens' => $r->input_tokens,
                'output_tokens' => $r->output_tokens,
                'model' => $r->model,
                'latency_ms' => $r->latency_ms,
                'from_cache' => $r->from_cache,
                'created_at' => $r->created_at->toIso8601String(),
            ]);

        return response()->json($results);
    }

    /**
     * GET /api/v1/optimizations/{id}
     */
    public function show(string $id): JsonResponse
    {
        $r = OptimizationResult::findOrFail($id);

        return response()->json([
            'id' => $r->id,
            'source_text' => $r->source_text,
            'optimized_text' => $r->optimized_text,
            'target_locale' => $r->target_locale,
            'optimization_type' => $r->optimization_type,
            'before_score' => $r->before_score,
            'after_score' => $r->after_score,
            'improvement' => $r->improvement,
            'improvement_pct' => round($r->improvement * 100, 1),
            'cost_cents' => $r->cost_cents,
            'input_tokens' => $r->input_tokens,
            'output_tokens' => $r->output_tokens,
            'model' => $r->model,
            'latency_ms' => $r->latency_ms,
            'from_cache' => $r->from_cache,
            'cached_at' => $r->cached_at?->toIso8601String(),
            'created_at' => $r->created_at->toIso8601String(),
        ]);
    }
}
