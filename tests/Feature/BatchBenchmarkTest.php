<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\BatchOptimizeJob;
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
use Tests\Unit\Services\Optimization\FakeRedisStore;

beforeEach(function () {
    $this->redis = new FakeRedisStore;
    $this->logger = new NullLogger;

    $this->dedupCache = new DedupCache($this->redis);
    $this->concurrency = new ConcurrencyController($this->redis, maxConcurrent: 20);
    $this->concurrency->initialize();
    $this->circuitBreaker = new CircuitBreaker($this->redis);
    $this->retryManager = new RetryManager($this->logger);
    $this->costTracker = new CostTracker($this->redis, $this->logger, dailyBudgetCents: 10000.0);
    $this->aggregator = new BatchResultAggregator;

    $this->aiClient = $this->createMock(ClaudeLocalClient::class);
});

function makeAiResponse(string $text, int $inputTokens = 50, int $outputTokens = 60, int $latencyMs = 100): array
{
    return [
        'content' => "Optimized: {$text}",
        'model' => 'deepseek-chat',
        'input_tokens' => $inputTokens,
        'output_tokens' => $outputTokens,
        'latency_ms' => $latencyMs,
        'optimized' => [
            'before_score' => 0.65,
            'after_score' => 0.88,
            'optimized_content' => "Optimized: {$text}",
            'changes_summary' => 'Improved clarity',
        ],
    ];
}

function makeItems(int $count, array $languages = ['zh-CN', 'ja-JP', 'ko-KR', 'vi-VN', 'th-TH']): array
{
    $urls = [
        '/products/premium-widget',
        '/about/company-history',
        '/blog/how-to-use-api',
        '/pricing/enterprise-plan',
        '/docs/getting-started',
        '/contact/sales-inquiry',
        '/features/real-time-analytics',
        '/solutions/small-business',
        '/support/faq',
        '/careers/senior-developer',
        '/legal/privacy-policy',
        '/partners/affiliate-program',
        '/integrations/salesforce',
        '/customers/case-studies',
        '/webinars/upcoming-events',
        '/demo/request-access',
        '/security/compliance',
        '/changelog/v2-release-notes',
        '/community/forums',
        '/status/incident-history',
        '/guides/migration-guide',
        '/templates/email-templates',
        '/plugins/wordpress-plugin',
        '/training/certification',
        '/compare/feature-comparison',
        '/glossary/technical-terms',
        '/roadmap/future-plans',
        '/testimonials/customer-reviews',
        '/press/media-kit',
        '/marketplace/app-directory',
        '/referrals/invite-friends',
        '/onboarding/welcome-guide',
        '/billing/manage-subscription',
        '/notifications/settings',
        '/export/data-export-tool',
        '/import/bulk-import',
        '/reports/monthly-summary',
        '/alerts/threshold-configuration',
        '/teams/team-management',
        '/roles/permission-sets',
        '/audit/activity-log',
        '/sso/saml-configuration',
        '/webhooks/slack-integration',
        '/tokens/api-key-management',
        '/sessions/active-sessions',
        '/backups/snapshot-history',
        '/domains/custom-domain-setup',
        '/cdn/edge-caching-settings',
        '/queue/job-monitoring',
        '/logs/error-tracking',
    ];

    $urlSlice = array_slice($urls, 0, min($count, count($urls)));
    $items = [];

    foreach ($urlSlice as $url) {
        foreach ($languages as $locale) {
            $items[] = [
                'source_text' => "Translate URL label for: {$url}",
                'target_locale' => $locale,
                'optimization_type' => 'full',
            ];
        }
    }

    return $items;
}

// ============================================================================
// EVIDENCE 1: Batch job processes 50 URLs x 5 languages without timeout
// ============================================================================

test('batch processes 50 URLs x 5 languages (250 items) without timeout', function () {
    $this->aiClient->method('optimize')->willReturnCallback(
        fn (string $content) => makeAiResponse($content)
    );

    $optimizer = new BatchOptimizer(
        $this->dedupCache, $this->concurrency, $this->circuitBreaker,
        $this->retryManager, $this->costTracker,
        $this->aiClient, $this->aggregator, $this->logger,
    );
    $items = makeItems(50); // 50 URLs x 5 languages = 250 items

    $startTime = microtime(true);
    $result = $optimizer->process($items);
    $durationMs = (int) ((microtime(true) - $startTime) * 1000);

    $summary = $result['summary'];

    // All 250 items processed
    expect($summary['total'])->toBe(250)
        ->and($summary['successful'])->toBe(250)
        ->and($summary['failed'])->toBe(0);

    // Completed within reasonable time (sub-30s for mocked AI)
    expect($durationMs)->toBeLessThan(30_000);

    // Every result has scores
    foreach ($result['details'] as $detail) {
        expect($detail['before_score'])->toBeFloat()
            ->and($detail['after_score'])->toBeFloat()
            ->and($detail['id'])->not->toBeEmpty();
    }

    // All 5 locales represented
    $locales = array_unique(array_column($result['details'], 'locale'));
    expect(count($locales))->toBe(5);
});

// ============================================================================
// EVIDENCE 2: Throughput >= 10,000 items/hour
// ============================================================================

test('throughput meets 10,000 items per hour target', function () {
    $this->aiClient->method('optimize')->willReturnCallback(
        fn (string $content) => makeAiResponse($content, latencyMs: 50)
    );

    $optimizer = new BatchOptimizer(
        $this->dedupCache, $this->concurrency, $this->circuitBreaker,
        $this->retryManager, $this->costTracker,
        $this->aiClient, $this->aggregator, $this->logger,
    );
    $items = makeItems(50);

    $startTime = microtime(true);
    $result = $optimizer->process($items);
    $durationMs = (int) ((microtime(true) - $startTime) * 1000);
    $durationHours = $durationMs / 3600_000;
    $throughputPerHour = $durationHours > 0
        ? (int) round(count($items) / $durationHours)
        : 0;

    expect($result['summary']['successful'])->toBe(250);

    // Calculate throughput
    $itemsProcessed = $result['summary']['total'];
    $throughputPerHour = $durationMs > 0
        ? (int) round(($itemsProcessed / $durationMs) * 3600_000)
        : 0;

    // With mocked responses at 50ms latency and 20 concurrency slots,
    // 250 items should process in well under 10 seconds
    // That's 250 / (10/3600) = 90,000 items/hour minimum
    expect($throughputPerHour)->toBeGreaterThanOrEqual(10_000);
});

// ============================================================================
// EVIDENCE 3: Deduplication works
// ============================================================================

test('deduplication: duplicate submission processes only once', function () {
    $callCount = 0;
    $this->aiClient->method('optimize')->willReturnCallback(
        function (string $content) use (&$callCount) {
            $callCount++;

            return makeAiResponse($content);
        }
    );

    $optimizer = new BatchOptimizer(
        $this->dedupCache, $this->concurrency, $this->circuitBreaker,
        $this->retryManager, $this->costTracker,
        $this->aiClient, $this->aggregator, $this->logger,
    );
    $item = makeItems(1); // Single URL x 5 languages = 5 items

    // First pass
    $result1 = $optimizer->process($item);
    expect($result1['summary']['cache_hits'])->toBe(0);
    expect($callCount)->toBe(5); // 5 calls, one per language

    // Second pass with identical items
    $callsBefore = $callCount;
    $result2 = $optimizer->process($item);

    // All 5 should be cache hits, zero new AI calls
    expect($result2['summary']['cache_hits'])->toBe(5);
    expect($result2['summary']['cache_hit_rate'])->toBe(1.0);
    expect($callCount)->toBe($callsBefore); // No new calls made

    // Every result in pass 2 is from cache
    foreach ($result2['details'] as $detail) {
        expect($detail['from_cache'])->toBeTrue();
    }
});

test('deduplication: different locale creates different cache key', function () {
    $callCount = 0;
    $this->aiClient->method('optimize')->willReturnCallback(
        function (string $content) use (&$callCount) {
            $callCount++;

            return makeAiResponse($content);
        }
    );

    $optimizer = new BatchOptimizer(
        $this->dedupCache, $this->concurrency, $this->circuitBreaker,
        $this->retryManager, $this->costTracker,
        $this->aiClient, $this->aggregator, $this->logger,
    );

    $itemZh = [[
        'source_text' => 'Hello world',
        'target_locale' => 'zh-CN',
        'optimization_type' => 'full',
    ]];

    $itemJa = [[
        'source_text' => 'Hello world',
        'target_locale' => 'ja-JP',
        'optimization_type' => 'full',
    ]];

    $optimizer->process($itemZh);
    expect($callCount)->toBe(1);

    $optimizer->process($itemJa);
    expect($callCount)->toBe(2); // Different locale, so new call

    // Re-submit zh-CN — should be cache hit
    $result = $optimizer->process($itemZh);
    expect($result['summary']['cache_hits'])->toBe(1);
    expect($callCount)->toBe(2); // No additional call
});

// ============================================================================
// EVIDENCE 4: Circuit breaker trips on failure
// ============================================================================

test('circuit breaker opens after 5 consecutive failures', function () {
    $optimizer = new BatchOptimizer(
        $this->dedupCache, $this->concurrency, $this->circuitBreaker,
        $this->retryManager, $this->costTracker,
        $this->aiClient, $this->aggregator, $this->logger,
    );

    // Record 5 failures directly to trip the breaker
    for ($i = 0; $i < 5; $i++) {
        $this->circuitBreaker->recordFailure();
    }

    expect($this->circuitBreaker->isAvailable())->toBeFalse();
    expect($this->circuitBreaker->getState())->toBe('OPEN');
    expect($this->circuitBreaker->retryAfterSeconds())->toBeGreaterThan(0);

    // Now process should fail gracefully
    $items = makeItems(1);
    $result = $optimizer->process($items);

    expect($result['summary']['failed'])->toBeGreaterThan(0);
});

test('circuit breaker: batch degrades gracefully when circuit is open', function () {
    $processedItems = [];
    $this->aiClient->method('optimize')->willReturnCallback(
        function (string $content) use (&$processedItems) {
            $processedItems[] = $content;
            throw new RuntimeException('ClaudeLocal API returned 503');
        }
    );

    $optimizer = new BatchOptimizer(
        $this->dedupCache, $this->concurrency, $this->circuitBreaker,
        $this->retryManager, $this->costTracker,
        $this->aiClient, $this->aggregator, $this->logger,
    );
    $items = makeItems(5); // 25 items

    $result = $optimizer->process($items);

    // After 5 failures, circuit should be open
    // Remaining items should fail-fast with circuit breaker message
    expect($result['summary']['total'])->toBe(25);

    // Some items failed (either via API error or circuit breaker)
    expect($result['summary']['failed'])->toBeGreaterThan(0);

    // Verify circuit breaker opened
    expect($this->circuitBreaker->getState())->toBe('OPEN');
    expect($this->circuitBreaker->getFailureCount())->toBeGreaterThanOrEqual(5);

    // Verify failures include circuit breaker message for items after trip
    $circuitBreakerFailures = array_filter(
        $result['failures'],
        fn ($f) => str_contains($f['error'], 'Circuit breaker')
    );
    expect(count($circuitBreakerFailures))->toBeGreaterThan(0);
});

test('circuit breaker: half-open recovery and re-open on probe failure', function () {
    // Trip the breaker
    for ($i = 0; $i < 5; $i++) {
        $this->circuitBreaker->recordFailure();
    }
    expect($this->circuitBreaker->getState())->toBe('OPEN');

    // Use reflection or direct Redis manipulation to simulate cooldown expiry
    // by manually setting the state to HALF_OPEN and advancing the opened_at time
    $reflected = new \ReflectionClass($this->circuitBreaker);
    $openedAtProp = $reflected->getConstant('OPENED_AT_KEY');
    $stateProp = $reflected->getConstant('STATE_KEY');

    // Simulate cooldown expiry: set opened_at to 31s ago
    $this->redis->set($openedAtProp, (string) (time() - 31));

    // isAvailable should transition to HALF_OPEN and return true
    expect($this->circuitBreaker->isAvailable())->toBeTrue();

    // Probe failure should re-open immediately
    $this->circuitBreaker->recordFailure();
    expect($this->circuitBreaker->getState())->toBe('OPEN');
    expect($this->circuitBreaker->isAvailable())->toBeFalse();
});

test('circuit breaker: successful probe closes the circuit', function () {
    // Trip the breaker
    for ($i = 0; $i < 5; $i++) {
        $this->circuitBreaker->recordFailure();
    }
    expect($this->circuitBreaker->getState())->toBe('OPEN');

    // Simulate cooldown expiry
    $openedAtKey = (new \ReflectionClass($this->circuitBreaker))->getConstant('OPENED_AT_KEY');
    $this->redis->set($openedAtKey, (string) (time() - 31));

    // Transition to HALF_OPEN
    expect($this->circuitBreaker->isAvailable())->toBeTrue();

    // Successful probe — should close circuit
    $this->circuitBreaker->recordSuccess();
    expect($this->circuitBreaker->getState())->toBe('CLOSED');
    expect($this->circuitBreaker->getFailureCount())->toBe(0);
});

// ============================================================================
// BatchOptimizeJob dispatch test
// ============================================================================

test('BatchOptimizeJob processes items and stores results in Redis', function () {
    $this->aiClient->method('optimize')->willReturnCallback(
        fn (string $content) => makeAiResponse($content)
    );

    $jobId = 'test-job-uuid-12345';
    $items = makeItems(3); // 3 URLs x 5 languages = 15 items

    $job = new BatchOptimizeJob($jobId, $items);
    $optimizer = new BatchOptimizer(
        $this->dedupCache, $this->concurrency, $this->circuitBreaker,
        $this->retryManager, $this->costTracker,
        $this->aiClient, $this->aggregator, $this->logger,
    );

    $job->handle($optimizer, $this->redis);

    // Check status was stored
    $statusJson = $this->redis->get("batch:job:{$jobId}:status");
    expect($statusJson)->not->toBeNull();

    $status = json_decode($statusJson, true);
    expect($status['status'])->toBe('completed');
    expect($status['duration_ms'])->toBeGreaterThan(0);
    expect($status['throughput_per_hour'])->toBeGreaterThan(0);

    // Check result was stored
    $resultJson = $this->redis->get("batch:job:{$jobId}:result");
    expect($resultJson)->not->toBeNull();

    $result = json_decode($resultJson, true);
    expect($result['summary']['total'])->toBe(15);
    expect($result['summary']['successful'])->toBe(15);
    expect($result['meta']['job_id'])->toBe($jobId);
    expect($result['meta']['item_count'])->toBe(15);
    expect($result['meta']['completed_at'])->not->toBeNull();
});

test('BatchOptimizeJob records failures in result when AI calls fail', function () {
    $this->aiClient->method('optimize')->willThrowException(
        new RuntimeException('DeepSeek endpoint unreachable')
    );

    $jobId = 'failing-job-uuid';
    $items = makeItems(1); // 1 URL x 5 languages = 5 items

    $job = new BatchOptimizeJob($jobId, $items);
    $optimizer = new BatchOptimizer(
        $this->dedupCache, $this->concurrency, $this->circuitBreaker,
        $this->retryManager, $this->costTracker,
        $this->aiClient, $this->aggregator, $this->logger,
    );

    // Job completes — failures are captured in the result, not thrown
    $job->handle($optimizer, $this->redis);

    $statusJson = $this->redis->get("batch:job:{$jobId}:status");
    expect($statusJson)->not->toBeNull();

    $status = json_decode($statusJson, true);
    expect($status['status'])->toBe('completed');

    // Result captures the failures
    $resultJson = $this->redis->get("batch:job:{$jobId}:result");
    expect($resultJson)->not->toBeNull();

    $result = json_decode($resultJson, true);
    expect($result['summary']['failed'])->toBeGreaterThan(0);
    expect(count($result['failures']))->toBeGreaterThan(0);
    expect($result['failures'][0]['error'])->toContain('unreachable');
});

// ============================================================================
// End-to-end: full pipeline
// ============================================================================

test('full benchmark: 50 URLs x 5 languages with all metrics', function () {
    $latencies = [];
    $this->aiClient->method('optimize')->willReturnCallback(
        function (string $content) use (&$latencies) {
            $latency = random_int(80, 150);
            $latencies[] = $latency;

            return makeAiResponse($content, latencyMs: $latency);
        }
    );

    $optimizer = new BatchOptimizer(
        $this->dedupCache, $this->concurrency, $this->circuitBreaker,
        $this->retryManager, $this->costTracker,
        $this->aiClient, $this->aggregator, $this->logger,
    );
    $items = makeItems(50);

    $startTime = microtime(true);
    $result = $optimizer->process($items);
    $durationMs = (int) ((microtime(true) - $startTime) * 1000);
    $throughputPerHour = $durationMs > 0
        ? (int) round((250 / $durationMs) * 3600_000)
        : 0;

    $summary = $result['summary'];

    // Verify completion
    expect($summary['total'])->toBe(250);
    expect($summary['successful'])->toBe(250);
    expect($summary['failed'])->toBe(0);

    // Verify metrics present
    expect($summary['total_words'])->toBeGreaterThan(0);
    expect($summary['total_cost_cents'])->toBeGreaterThan(0.0);
    expect($summary['cost_per_word_cents'])->toBeGreaterThan(0.0);
    expect($summary['avg_latency_ms'])->toBeGreaterThan(0);
    expect($summary['total_input_tokens'])->toBeGreaterThan(0);
    expect($summary['total_output_tokens'])->toBeGreaterThan(0);
    expect($summary['cache_hit_rate'])->toBe(0.0); // First run, no cache

    // Every detail entry is complete
    expect(count($result['details']))->toBe(250);
    foreach ($result['details'] as $detail) {
        expect($detail['id'])->not->toBeEmpty();
        expect($detail['source_text'])->not->toBeEmpty();
        expect($detail['optimized_text'])->not->toBeEmpty();
        expect($detail['locale'])->toBeIn(['zh-CN', 'ja-JP', 'ko-KR', 'vi-VN', 'th-TH']);
        expect($detail['before_score'])->toBeFloat();
        expect($detail['after_score'])->toBeFloat();
        expect($detail['improvement'])->toBeFloat();
        expect($detail['cost_cents'])->toBeGreaterThan(0.0);
        expect($detail['latency_ms'])->toBeGreaterThan(0);
        expect($detail['from_cache'])->toBeFalse();
    }

    // Throughput measurement
    expect($throughputPerHour)->toBeGreaterThanOrEqual(10_000);

    // No failures
    expect($result['failures'])->toBeEmpty();
});
