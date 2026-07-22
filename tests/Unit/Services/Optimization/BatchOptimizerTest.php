<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Optimization;

use App\Services\ClaudeLocal\ClaudeLocalClient;
use App\Services\Optimization\BatchOptimizer;
use App\Services\Optimization\BatchResultAggregator;
use App\Services\Optimization\CircuitBreaker;
use App\Services\Optimization\ConcurrencyController;
use App\Services\Optimization\CostTracker;
use App\Services\Optimization\DedupCache;
use App\Services\Optimization\RetryManager;
use Psr\Log\NullLogger;
use RuntimeException;

beforeEach(function () {
    $this->redis = new FakeRedisStore();
    $this->logger = new NullLogger();

    $this->dedupCache = new DedupCache($this->redis);
    $this->concurrency = new ConcurrencyController($this->redis, maxConcurrent: 10);
    $this->concurrency->initialize();
    $this->circuitBreaker = new CircuitBreaker($this->redis);
    $this->retryManager = new RetryManager($this->logger);
    $this->costTracker = new CostTracker($this->redis, $this->logger, dailyBudgetCents: 10000.0);
    $this->aggregator = new BatchResultAggregator();

    $this->aiClient = $this->createMock(ClaudeLocalClient::class);
});

function makeAiResponse(string $optimizedText, int $inputTokens = 10, int $outputTokens = 12, int $latencyMs = 500): array
{
    return [
        'content' => $optimizedText,
        'model' => 'deepseek-chat',
        'input_tokens' => $inputTokens,
        'output_tokens' => $outputTokens,
        'latency_ms' => $latencyMs,
        'optimized' => [
            'before_score' => 0.7,
            'after_score' => 0.85,
            'optimized_content' => $optimizedText,
            'changes_summary' => 'Improved clarity and grammar',
        ],
    ];
}

test('submit returns job estimate', function () {
    $optimizer = new BatchOptimizer(
        $this->dedupCache,
        $this->concurrency,
        $this->circuitBreaker,
        $this->retryManager,
        $this->costTracker,
        $this->aiClient,
        $this->aggregator,
        $this->logger,
    );

    $estimate = $optimizer->submit([
        ['source_text' => 'Hello world', 'target_locale' => 'zh-CN', 'optimization_type' => 'grammar'],
        ['source_text' => 'Another text', 'target_locale' => 'ja-JP', 'optimization_type' => 'full'],
    ]);

    expect($estimate)->toHaveKey('job_id')
        ->and($estimate)->toHaveKey('estimated_cost')
        ->and($estimate)->toHaveKey('estimated_duration')
        ->and($estimate['status'])->toBe('accepted');
});

test('process returns dedup cache hit for repeated content', function () {
    $this->aiClient->method('optimize')->willReturn(makeAiResponse('Optimized text'));

    $optimizer = new BatchOptimizer(
        $this->dedupCache,
        $this->concurrency,
        $this->circuitBreaker,
        $this->retryManager,
        $this->costTracker,
        $this->aiClient,
        $this->aggregator,
        $this->logger,
    );

    $items = [
        ['source_text' => 'Hello world', 'target_locale' => 'zh-CN', 'optimization_type' => 'grammar'],
    ];

    $result1 = $optimizer->process($items);
    expect($result1['summary']['total'])->toBe(1);

    $result2 = $optimizer->process($items);

    expect($result2['summary']['cache_hits'])->toBe(1)
        ->and($result2['summary']['cache_hit_rate'])->toBe(1.0)
        ->and($result2['details'][0]['from_cache'])->toBeTrue();
});

test('process respects circuit breaker', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->circuitBreaker->recordFailure();
    }

    $optimizer = new BatchOptimizer(
        $this->dedupCache,
        $this->concurrency,
        $this->circuitBreaker,
        $this->retryManager,
        $this->costTracker,
        $this->aiClient,
        $this->logger,
        $this->aggregator,
    );

    $items = [
        ['source_text' => 'Test', 'target_locale' => 'zh-CN', 'optimization_type' => 'grammar'],
    ];

    $result = $optimizer->process($items);

    expect($result['summary']['failed'])->toBe(1)
        ->and($result['failures'][0]['error'])->toContain('Circuit breaker');
});

test('process tracks per-item success and failure', function () {
    $this->aiClient->method('optimize')->willReturnCallback(
        function (string $content, string $objective, array $parameters = []) {
            if ($content === 'fail-me') {
                throw new RuntimeException('ClaudeLocal API returned 503');
            }
            return makeAiResponse("Optimized: {$content}");
        },
    );

    $optimizer = new BatchOptimizer(
        $this->dedupCache,
        $this->concurrency,
        $this->circuitBreaker,
        $this->retryManager,
        $this->costTracker,
        $this->aiClient,
        $this->aggregator,
        $this->logger,
    );

    $items = [
        ['source_text' => 'good-one', 'target_locale' => 'fr-FR', 'optimization_type' => 'grammar'],
        ['source_text' => 'fail-me', 'target_locale' => 'de-DE', 'optimization_type' => 'clarity'],
        ['source_text' => 'good-two', 'target_locale' => 'es-ES', 'optimization_type' => 'tone'],
    ];

    $result = $optimizer->process($items);

    expect($result['summary']['total'])->toBe(3)
        ->and($result['summary']['successful'])->toBe(2)
        ->and($result['summary']['failed'])->toBe(1)
        ->and(count($result['details']))->toBe(2)
        ->and(count($result['failures']))->toBe(1);
});

test('process includes before/after scores in every result', function () {
    $this->aiClient->method('optimize')->willReturn(makeAiResponse('Refined and improved text'));

    $optimizer = new BatchOptimizer(
        $this->dedupCache,
        $this->concurrency,
        $this->circuitBreaker,
        $this->retryManager,
        $this->costTracker,
        $this->aiClient,
        $this->aggregator,
        $this->logger,
    );

    $result = $optimizer->process([
        ['source_text' => 'Original text needing work', 'target_locale' => 'en-US', 'optimization_type' => 'full'],
    ]);

    $detail = $result['details'][0];
    expect($detail['before_score'])->toBeFloat()
        ->and($detail['after_score'])->toBeFloat()
        ->and($detail['improvement'])->toBeFloat();
});

test('submit returns 202-style estimate for large batches', function () {
    $optimizer = new BatchOptimizer(
        $this->dedupCache, $this->concurrency, $this->circuitBreaker,
        $this->retryManager, $this->costTracker,
        $this->aiClient, $this->aggregator, $this->logger,
    );

    $items = array_map(
        fn($i) => [
            'source_text' => "Test text number {$i}",
            'target_locale' => 'zh-CN',
            'optimization_type' => 'grammar',
        ],
        range(1, 50),
    );

    $estimate = $optimizer->submit($items);

    expect($estimate['status'])->toBe('accepted')
        ->and($estimate['estimated_cost'])->toBeGreaterThan(0.0);
});
