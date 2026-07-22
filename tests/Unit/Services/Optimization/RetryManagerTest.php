<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Optimization;

use App\Services\Optimization\DeadLetterQueue;
use App\Services\Optimization\DeepSeekException;
use App\Services\Optimization\RetryManager;
use Psr\Log\NullLogger;

beforeEach(function () {
    $this->logger = new NullLogger;
    $this->manager = new RetryManager($this->logger);
});

test('execute returns result on first attempt', function () {
    $result = $this->manager->execute(fn () => 'success');
    expect($result)->toBe('success');
});

test('execute retries on transient failure and succeeds', function () {
    $attempts = 0;
    $result = $this->manager->execute(function () use (&$attempts) {
        $attempts++;
        if ($attempts < 2) {
            throw DeepSeekException::serverError(500);
        }

        return 'recovered';
    });

    expect($result)->toBe('recovered')
        ->and($attempts)->toBe(2);
});

test('execute throws after exhausting all retries', function () {
    $attempts = 0;

    expect(function () use (&$attempts) {
        $this->manager->execute(function () use (&$attempts) {
            $attempts++;
            throw DeepSeekException::serverError(500);
        });
    })->toThrow(DeepSeekException::class);

    // 1 initial + 3 retries = 4 total attempts
    expect($attempts)->toBe(4);
});

test('execute retries with exponential backoff pattern', function () {
    $timestamps = [];
    $attempts = 0;

    try {
        $this->manager->execute(function () use (&$attempts, &$timestamps) {
            $attempts++;
            $timestamps[] = microtime(true);
            throw DeepSeekException::serverError(500);
        });
    } catch (DeepSeekException) {
    }

    expect($attempts)->toBe(4);
    expect(count($timestamps))->toBe(4);

    // Verify delays between attempts (approximate, with jitter tolerance)
    if (count($timestamps) >= 4) {
        $d1 = (int) round(($timestamps[1] - $timestamps[0]) * 1000);
        $d2 = (int) round(($timestamps[2] - $timestamps[1]) * 1000);
        $d3 = (int) round(($timestamps[3] - $timestamps[2]) * 1000);

        // Base delays: 1000, 2000, 4000. With 30% jitter: 700-1300, 1400-2600, 2800-5200
        expect($d1)->toBeGreaterThanOrEqual(500)
            ->and($d1)->toBeLessThanOrEqual(1500);
        expect($d2)->toBeGreaterThanOrEqual(1000)
            ->and($d2)->toBeLessThanOrEqual(3000);
        expect($d3)->toBeGreaterThanOrEqual(2000)
            ->and($d3)->toBeLessThanOrEqual(5500);
    }
});

test('executeBatch succeeds on all items', function () {
    $items = ['alpha', 'beta', 'gamma'];
    $results = $this->manager->executeBatch($items, fn (string $text) => strtoupper($text));

    expect(count($results))->toBe(3);
});

test('executeBatch retries failed items granularly', function () {
    $failCounts = [];
    $results = $this->manager->executeBatch(
        ['a', 'b', 'c'],
        function (string $item) use (&$failCounts) {
            if (! isset($failCounts[$item])) {
                $failCounts[$item] = 0;
            }
            $failCounts[$item]++;
            if ($failCounts[$item] < 2) {
                throw DeepSeekException::serverError(503);
            }

            return strtoupper($item);
        },
    );

    expect(count($results))->toBe(3);
});

test('executeBatch returns partial results when some items exhaust retries', function () {
    $results = $this->manager->executeBatch(
        ['ok', 'fail'],
        function (string $item) {
            if ($item === 'fail') {
                throw DeepSeekException::serverError(503);
            }

            return strtoupper($item);
        },
    );

    // 'ok' succeeds, 'fail' exhausts all retries
    expect(count($results))->toBe(1)
        ->and($results[0]['index'])->toBe(0);
});

test('executeBatch sends exhausted retries to dead letter queue', function () {
    $redis = new FakeRedisStore;
    $dlq = new DeadLetterQueue($redis, new NullLogger);
    $manager = new RetryManager(new NullLogger, $dlq);

    $results = $manager->executeBatch(
        ['good', 'bad'],
        function (string $item) {
            if ($item === 'bad') {
                throw DeepSeekException::serverError(503);
            }

            return strtoupper($item);
        },
    );

    // Good item succeeds
    expect(count($results))->toBe(1)
        ->and($results[0]['result'])->toBe('GOOD');

    // Bad item is in DLQ
    expect($dlq->count())->toBe(1);
    $entries = $dlq->list();
    expect($entries[0]['source_text'])->toBe('bad')
        ->and($entries[0]['error'])->toContain('503');
});

test('executeBatch without DLQ does not throw', function () {
    $manager = new RetryManager(new NullLogger);

    $results = $manager->executeBatch(
        ['always-fails'],
        function () {
            throw DeepSeekException::serverError(500);
        },
    );

    expect($results)->toBeEmpty();
});
