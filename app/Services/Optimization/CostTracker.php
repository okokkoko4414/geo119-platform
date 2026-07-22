<?php

declare(strict_types=1);

namespace App\Services\Optimization;

use App\Services\Contracts\RedisStore;
use Psr\Log\LoggerInterface;

final class CostTracker
{
    private const DEEPSEEK_INPUT_PRICE_PER_1M = 0.14;  // $0.14 per 1M input tokens

    private const DEEPSEEK_OUTPUT_PRICE_PER_1M = 0.28; // $0.28 per 1M output tokens

    private const DAILY_BUDGET_KEY = 'cost:daily_budget';

    private const DAILY_SPEND_KEY_PREFIX = 'cost:daily:';

    public function __construct(
        private readonly RedisStore $redis,
        private readonly LoggerInterface $logger,
        private readonly float $dailyBudgetCents = 100.0, // $1.00 default
    ) {}

    /**
     * Calculate the cost of a DeepSeek response in cents.
     */
    public function calculateCost(DeepSeekResponse $response): float
    {
        $inputCost = ($response->inputTokens / 1_000_000) * (self::DEEPSEEK_INPUT_PRICE_PER_1M * 100);
        $outputCost = ($response->outputTokens / 1_000_000) * (self::DEEPSEEK_OUTPUT_PRICE_PER_1M * 100);

        return round($inputCost + $outputCost, 6);
    }

    /**
     * Record a cost log entry for a DeepSeek response.
     */
    public function record(DeepSeekResponse $response, string $operationType): void
    {
        $cost = $this->calculateCost($response);

        $this->redis->incrbyfloat(
            $this->dailySpendKey(),
            $cost,
        );

        $this->logger->info('CostTracker: recorded cost', [
            'operation_type' => $operationType,
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'model' => $response->model,
            'latency_ms' => $response->latencyMs,
            'cost_cents' => $cost,
            'locale' => $response->locale,
        ]);
    }

    /**
     * Check if a new request would exceed the daily budget.
     * Returns false if the estimated cost would push spending over the cap.
     */
    public function isWithinBudget(float $estimatedCostCents): bool
    {
        $currentSpend = $this->getDailySpend();

        return ($currentSpend + $estimatedCostCents) <= $this->dailyBudgetCents;
    }

    public function getDailySpend(): float
    {
        return (float) ($this->redis->get($this->dailySpendKey()) ?: 0.0);
    }

    public function getDailyBudgetCents(): float
    {
        return $this->dailyBudgetCents;
    }

    /**
     * Calculate cost per word from aggregated data.
     */
    public function costPerWord(int $totalWords, float $totalCostCents): float
    {
        if ($totalWords === 0) {
            return 0.0;
        }

        return $totalCostCents / $totalWords;
    }

    /**
     * Reset daily spend — for testing or budget period rollover.
     */
    public function resetDailySpend(): void
    {
        $this->redis->del($this->dailySpendKey());
    }

    private function dailySpendKey(): string
    {
        return self::DAILY_SPEND_KEY_PREFIX.date('Y-m-d');
    }
}
