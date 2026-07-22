<?php

declare(strict_types=1);

namespace App\Services\ClaudeLocal;

use App\Models\CostLog;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;

class CostTracker
{
    private const KEY_PREFIX = 'cost_tracker:%s:';

    private string $name;

    private Connection $redis;

    // DeepSeek pricing (cents per 1M tokens)
    private const COST_PER_1M_INPUT = 14;   // $0.14/1M input

    private const COST_PER_1M_OUTPUT = 28;  // $0.28/1M output

    public function __construct(string $name = 'default')
    {
        $this->name = $name;
        $this->redis = Redis::connection('cache');
    }

    public function record(string $model, int $inputTokens, int $outputTokens, int $latencyMs): void
    {
        $costCents = (int) ceil(
            ($inputTokens / 1_000_000) * self::COST_PER_1M_INPUT
            + ($outputTokens / 1_000_000) * self::COST_PER_1M_OUTPUT
        );

        // Persist to cost_logs DB table for durability across workers
        CostLog::create([
            'operation_type' => 'translation',
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'model' => $model,
            'latency_ms' => $latencyMs,
            'cost_cents' => $costCents,
            'source_text_hash' => '',
            'locale' => substr($this->name, 0, 5),
            'log_date' => now()->toDateString(),
        ]);

        // Maintain rolling counters in Redis for O(1) summary access
        $prefix = sprintf(self::KEY_PREFIX, $this->name);
        $this->redis->incrby("{$prefix}requests", 1);
        $this->redis->incrby("{$prefix}input_tokens", $inputTokens);
        $this->redis->incrby("{$prefix}output_tokens", $outputTokens);
        $this->redis->incrby("{$prefix}cost_cents", $costCents);
        $this->redis->incrby("{$prefix}latency_ms", $latencyMs);

        // Maintain a rolling list of recent requests (keep last 100)
        $requestKey = "{$prefix}recent";
        $this->redis->lpush($requestKey, json_encode([
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_cents' => $costCents,
            'latency_ms' => $latencyMs,
            'timestamp' => now()->toIso8601String(),
        ]));
        $this->redis->ltrim($requestKey, 0, 99);
        $this->redis->expire($requestKey, 86400);
    }

    public function getSummary(): array
    {
        $prefix = sprintf(self::KEY_PREFIX, $this->name);

        $totalRequests = (int) $this->redis->get("{$prefix}requests");
        $totalInputTokens = (int) $this->redis->get("{$prefix}input_tokens");
        $totalOutputTokens = (int) $this->redis->get("{$prefix}output_tokens");
        $totalCostCents = (int) $this->redis->get("{$prefix}cost_cents");
        $totalLatencyMs = (int) $this->redis->get("{$prefix}latency_ms");

        return [
            'total_requests' => $totalRequests,
            'total_input_tokens' => $totalInputTokens,
            'total_output_tokens' => $totalOutputTokens,
            'total_cost_cents' => $totalCostCents,
            'total_latency_ms' => $totalLatencyMs,
            'avg_latency_ms' => $totalRequests > 0
                ? (int) ($totalLatencyMs / $totalRequests)
                : 0,
        ];
    }

    public function getRecentRequests(int $limit = 20): array
    {
        $prefix = sprintf(self::KEY_PREFIX, $this->name);
        $recent = $this->redis->lrange("{$prefix}recent", 0, $limit - 1);

        return array_map(
            fn (string $json): array => json_decode($json, true) ?? [],
            $recent
        );
    }
}
