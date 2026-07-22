<?php

declare(strict_types=1);

namespace App\Services\Optimization;

use App\Models\OptimizationResult as OptimizationResultModel;
use App\Services\ClaudeLocal\ClaudeLocalClient;
use DateTimeImmutable;
use Illuminate\Support\Facades\Redis;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use RuntimeException;

final class BatchOptimizer
{
    private const QUEUE_WAIT_TIMEOUT_MS = 30_000;

    public function __construct(
        private readonly DedupCache $dedupCache,
        private readonly ConcurrencyController $concurrencyController,
        private readonly CircuitBreaker $circuitBreaker,
        private readonly RetryManager $retryManager,
        private readonly CostTracker $costTracker,
        private readonly ClaudeLocalClient $aiClient,
        private readonly BatchResultAggregator $aggregator,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Submit a batch of texts for optimization.
     *
     * @param  array<int, array{source_text: string, target_locale: string, optimization_type: string}>  $items
     * @return array{job_id: string, estimated_cost: float, estimated_duration: int, status: string}
     */
    public function submit(array $items): array
    {
        $jobId = Uuid::uuid4()->toString();
        $totalChars = array_sum(array_map(fn ($i) => mb_strlen($i['source_text']), $items));
        $estimatedTokens = (int) ceil($totalChars / 4);
        $estimatedCost = $this->costTracker->calculateCost(new DeepSeekResponse(
            optimizedText: '',
            inputTokens: $estimatedTokens,
            outputTokens: $estimatedTokens,
            model: 'deepseek-chat',
            latencyMs: 0,
            sourceText: '',
            locale: '',
        ));
        $estimatedDuration = (int) ceil(count($items) * 1.5);

        return [
            'job_id' => $jobId,
            'estimated_cost' => round($estimatedCost, 6),
            'estimated_duration' => $estimatedDuration,
            'status' => 'accepted',
        ];
    }

    /**
     * Process a batch synchronously.
     *
     * @param  array<int, array{source_text: string, target_locale: string, optimization_type: string}>  $items
     */
    public function process(array $items): array
    {
        $results = [];
        $failures = [];

        foreach ($items as $index => $item) {
            $type = OptimizationType::from($item['optimization_type']);

            try {
                $result = $this->optimizeOne(
                    sourceText: $item['source_text'],
                    locale: $item['target_locale'],
                    type: $type,
                );
                $results[] = $result;
            } catch (DeepSeekException $e) {
                $failures[] = BatchResultAggregator::failureEntry(
                    index: $index,
                    sourceText: $item['source_text'],
                    locale: $item['target_locale'],
                    type: $item['optimization_type'],
                    error: $e->getMessage(),
                );
            }
        }

        return $this->aggregator->aggregate($results, $failures);
    }

    /**
     * Optimize a single text through the full pipeline.
     *
     * Pipeline: DedupCache -> ConcurrencyCtrl -> CircuitBreaker -> DeepSeek -> Score + Track
     *
     * @throws DeepSeekException
     */
    private function optimizeOne(string $sourceText, string $locale, OptimizationType $type): OptimizationResult
    {
        // 1. Check dedup cache
        $cached = $this->dedupCache->get($sourceText, $locale, $type);
        if ($cached !== null) {
            $this->logger->debug('BatchOptimizer: dedup cache hit', [
                'hash' => $this->dedupCache->hashKey($sourceText, $locale, $type),
                'locale' => $locale,
            ]);

            return $cached;
        }

        // 2. Acquire processing lock (handle concurrent identical requests)
        if (! $this->dedupCache->acquireLock($sourceText, $locale, $type)) {
            $this->logger->debug('BatchOptimizer: waiting for in-flight result');
            $polled = $this->dedupCache->pollForResult($sourceText, $locale, $type, self::QUEUE_WAIT_TIMEOUT_MS);
            if ($polled !== null) {
                return $polled;
            }
        }

        try {
            // 3. Acquire concurrency slot
            $waitStart = microtime(true);
            $acquired = false;
            while (! $acquired) {
                $acquired = $this->concurrencyController->acquire();
                if (! $acquired) {
                    $elapsed = (microtime(true) - $waitStart) * 1000;
                    if ($elapsed >= self::QUEUE_WAIT_TIMEOUT_MS) {
                        throw new DeepSeekException('Concurrency slot wait timeout — all workers busy');
                    }
                    usleep(50_000);
                }
            }

            try {
                // 4. Check circuit breaker
                if (! $this->circuitBreaker->isAvailable()) {
                    $retryAfter = $this->circuitBreaker->retryAfterSeconds();
                    throw new DeepSeekException(
                        "Circuit breaker is OPEN. Retry after {$retryAfter}s."
                    );
                }

                // 5. Check cost budget
                $estimatedCost = $this->costTracker->calculateCost(new DeepSeekResponse(
                    optimizedText: '',
                    inputTokens: (int) ceil(mb_strlen($sourceText) / 2),
                    outputTokens: (int) ceil(mb_strlen($sourceText) / 2),
                    model: 'deepseek-chat',
                    latencyMs: 0,
                    sourceText: $sourceText,
                    locale: $locale,
                ));

                if (! $this->costTracker->isWithinBudget($estimatedCost)) {
                    throw new DeepSeekException(
                        'Daily cost budget exceeded. Request rejected. Budget resets at midnight UTC.'
                    );
                }

                // 6. Call DeepSeek with retry (bridging existing ClaudeLocalClient interface)
                $aiResponse = $this->retryManager->execute(function () use ($sourceText, $locale, $type) {
                    try {
                        return $this->aiClient->optimize(
                            $sourceText,
                            $type->value,
                            ['locale' => $locale],
                        );
                    } catch (RuntimeException $e) {
                        throw new DeepSeekException($e->getMessage(), $e->getCode(), $e);
                    }
                });

                $response = new DeepSeekResponse(
                    optimizedText: $aiResponse['optimized']['optimized_content'] ?? $aiResponse['content'] ?? '',
                    inputTokens: (int) ($aiResponse['input_tokens'] ?? 0),
                    outputTokens: (int) ($aiResponse['output_tokens'] ?? 0),
                    model: $aiResponse['model'] ?? 'deepseek-chat',
                    latencyMs: (int) ($aiResponse['latency_ms'] ?? 0),
                    sourceText: $sourceText,
                    locale: $locale,
                );

                // 7. Circuit breaker: record success
                $this->circuitBreaker->recordSuccess();

                // 8. Compute before/after scores
                $score = BeforeAfterScore::compute($sourceText, $response->optimizedText, $type);

                // 9. Track cost
                $this->costTracker->record($response, $type->value);

                // 10. Build result
                $result = new OptimizationResult(
                    id: Uuid::uuid4()->toString(),
                    sourceText: $sourceText,
                    optimizedText: $response->optimizedText,
                    targetLocale: $locale,
                    optimizationType: $type,
                    score: $score,
                    costCents: $this->costTracker->calculateCost($response),
                    inputTokens: $response->inputTokens,
                    outputTokens: $response->outputTokens,
                    model: $response->model,
                    latencyMs: $response->latencyMs,
                    cachedAt: new DateTimeImmutable,
                    fromCache: false,
                );

                // 11. Store in dedup cache
                $this->dedupCache->set($sourceText, $locale, $type, $result);

                // 12. Persist to database + publish to stream for real-time dashboard
                $this->persistResult($result);

                return $result;
            } finally {
                $this->concurrencyController->release();
            }
        } catch (DeepSeekException $e) {
            // Circuit breaker: record failure (skip recording for budget/circuit-open rejections)
            if (
                ! str_contains($e->getMessage(), 'Cost budget exceeded')
                && ! str_contains($e->getMessage(), 'Circuit breaker is OPEN')
                && ! str_contains($e->getMessage(), 'Concurrency slot wait timeout')
            ) {
                $this->circuitBreaker->recordFailure();
            }

            throw $e;
        } finally {
            $this->dedupCache->releaseLock($sourceText, $locale, $type);
        }
    }

    private function persistResult(OptimizationResult $result): void
    {
        $sourceHash = $this->dedupCache->hashKey(
            $result->sourceText,
            $result->targetLocale,
            $result->optimizationType,
        );

        OptimizationResultModel::create([
            'id' => $result->id,
            'source_text' => $result->sourceText,
            'optimized_text' => $result->optimizedText,
            'target_locale' => $result->targetLocale,
            'optimization_type' => $result->optimizationType->value,
            'before_score' => $result->score->beforeScore,
            'after_score' => $result->score->afterScore,
            'improvement' => $result->score->improvement,
            'cost_cents' => $result->costCents,
            'input_tokens' => $result->inputTokens,
            'output_tokens' => $result->outputTokens,
            'model' => $result->model,
            'latency_ms' => $result->latencyMs,
            'source_hash' => $sourceHash,
            'from_cache' => $result->fromCache,
            'cached_at' => $result->cachedAt,
        ]);

        Redis::xadd('optimizations:stream', '*', [
            'id' => $result->id,
            'target_locale' => $result->targetLocale,
            'optimization_type' => $result->optimizationType->value,
            'before_score' => (string) $result->score->beforeScore,
            'after_score' => (string) $result->score->afterScore,
            'improvement' => (string) $result->score->improvement,
            'timestamp' => (string) now()->timestamp,
        ]);

        Redis::xtrim('optimizations:stream', 10_000, true);
    }
}
