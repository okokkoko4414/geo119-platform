<?php

declare(strict_types=1);

namespace Tests\Unit\Services\ClaudeLocal;

use App\Services\ClaudeLocal\RateLimiter;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    $this->limiter = new RateLimiter('test', maxTokens: 10, refillRate: 10.0);
});

afterEach(function () {
    Redis::connection('cache')->del(
        'rate_limiter:test:tokens',
        'rate_limiter:test:last_refill',
    );
});

test('allows requests up to capacity', function () {
    for ($i = 0; $i < 10; $i++) {
        expect($this->limiter->tryAcquire())->toBeTrue();
    }
});

test('blocks when capacity exhausted', function () {
    for ($i = 0; $i < 10; $i++) {
        $this->limiter->tryAcquire();
    }

    expect($this->limiter->tryAcquire())->toBeFalse();
});

test('getAvailableTokens returns current balance', function () {
    expect($this->limiter->getAvailableTokens())->toBe(10.0);

    $this->limiter->tryAcquire();
    expect($this->limiter->getAvailableTokens())->toBeGreaterThanOrEqual(9.0)
        ->and($this->limiter->getAvailableTokens())->toBeLessThan(10.0);
});

test('refill restores tokens over time', function () {
    $fastRefill = new RateLimiter('fast', maxTokens: 5, refillRate: 1000.0);

    for ($i = 0; $i < 5; $i++) {
        $fastRefill->tryAcquire();
    }
    expect($fastRefill->tryAcquire())->toBeFalse();

    usleep(10_000);
    expect($fastRefill->getAvailableTokens())->toBeGreaterThan(0.0);

    Redis::connection('cache')->del('rate_limiter:fast:tokens', 'rate_limiter:fast:last_refill');
});

test('default constructor creates valid limiter', function () {
    $limiter = new RateLimiter('default-test');
    expect($limiter->getAvailableTokens())->toBe(500.0)
        ->and($limiter->tryAcquire())->toBeTrue();

    Redis::connection('cache')->del('rate_limiter:default-test:tokens', 'rate_limiter:default-test:last_refill');
});

test('does not exceed max tokens', function () {
    $this->limiter->tryAcquire();
    $this->limiter->tryAcquire();

    $available = $this->limiter->getAvailableTokens();
    expect($available)->toBeLessThanOrEqual(10.0);
});
