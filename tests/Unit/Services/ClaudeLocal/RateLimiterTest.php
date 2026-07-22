<?php

declare(strict_types=1);

namespace Tests\Unit\Services\ClaudeLocal;

use App\Services\ClaudeLocal\RateLimiter;

beforeEach(function () {
    $this->limiter = new RateLimiter(maxTokens: 10, refillRate: 10.0);
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
    $fastRefill = new RateLimiter(maxTokens: 5, refillRate: 1000.0);

    for ($i = 0; $i < 5; $i++) {
        $fastRefill->tryAcquire();
    }
    expect($fastRefill->tryAcquire())->toBeFalse();

    usleep(10_000);
    expect($fastRefill->getAvailableTokens())->toBeGreaterThan(0.0);
});

test('default constructor creates valid limiter', function () {
    $limiter = new RateLimiter;
    expect($limiter->getAvailableTokens())->toBe(500.0)
        ->and($limiter->tryAcquire())->toBeTrue();
});

test('does not exceed max tokens', function () {
    $this->limiter->tryAcquire();
    $this->limiter->tryAcquire();

    $available = $this->limiter->getAvailableTokens();
    expect($available)->toBeLessThanOrEqual(10.0);
});
