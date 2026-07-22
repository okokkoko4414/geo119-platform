<?php

namespace App\Services\ClaudeLocal;

use Illuminate\Support\Facades\Log;

class CostTracker
{
    private array $requests = [];

    private int $totalInputTokens = 0;

    private int $totalOutputTokens = 0;

    private int $totalCostCents = 0;

    private int $totalLatencyMs = 0;

    // DeepSeek pricing (cents per 1M tokens)
    private const COST_PER_1M_INPUT = 14;   // $0.14/1M input

    private const COST_PER_1M_OUTPUT = 28;  // $0.28/1M output

    public function record(string $model, int $inputTokens, int $outputTokens, int $latencyMs): void
    {
        $costCents = (int) ceil(
            ($inputTokens / 1_000_000) * self::COST_PER_1M_INPUT
            + ($outputTokens / 1_000_000) * self::COST_PER_1M_OUTPUT
        );

        $this->requests[] = [
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_cents' => $costCents,
            'latency_ms' => $latencyMs,
            'timestamp' => now()->toIso8601String(),
        ];

        $this->totalInputTokens += $inputTokens;
        $this->totalOutputTokens += $outputTokens;
        $this->totalCostCents += $costCents;
        $this->totalLatencyMs += $latencyMs;

        Log::info('ClaudeLocal cost tracked', [
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_cents' => $costCents,
            'latency_ms' => $latencyMs,
        ]);
    }

    public function getSummary(): array
    {
        return [
            'total_requests' => count($this->requests),
            'total_input_tokens' => $this->totalInputTokens,
            'total_output_tokens' => $this->totalOutputTokens,
            'total_cost_cents' => $this->totalCostCents,
            'total_latency_ms' => $this->totalLatencyMs,
            'avg_latency_ms' => count($this->requests) > 0
                ? (int) ($this->totalLatencyMs / count($this->requests))
                : 0,
        ];
    }

    public function getRecentRequests(int $limit = 20): array
    {
        return array_slice($this->requests, -$limit);
    }
}
