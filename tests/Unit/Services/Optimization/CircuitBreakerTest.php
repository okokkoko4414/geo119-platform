<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Optimization;

use App\Services\Optimization\CircuitBreaker;

beforeEach(function () {
    $this->redis = new FakeRedisStore;
    $this->breaker = new CircuitBreaker($this->redis);
});

test('initial state is CLOSED and available', function () {
    expect($this->breaker->getState())->toBe('CLOSED')
        ->and($this->breaker->isAvailable())->toBeTrue();
});

test('B3.5: circuit breaker opens after 5 consecutive failures', function () {
    for ($i = 0; $i < 5; $i++) {
        expect($this->breaker->isAvailable())->toBeTrue();
        $this->breaker->recordFailure();
    }

    expect($this->breaker->getState())->toBe('OPEN')
        ->and($this->breaker->isAvailable())->toBeFalse();
});

test('B3.5: 6th request returns unavailable when circuit is OPEN', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->breaker->recordFailure();
    }

    expect($this->breaker->isAvailable())->toBeFalse();
});

test('success resets failures counter', function () {
    $this->breaker->recordFailure();
    $this->breaker->recordFailure();
    expect($this->breaker->getFailureCount())->toBe(2);

    $this->breaker->recordSuccess();

    expect($this->breaker->getState())->toBe('CLOSED')
        ->and($this->breaker->getFailureCount())->toBe(0);
});

test('B3.6: circuit breaker goes to HALF_OPEN and recovers on success', function () {
    // Trip the breaker
    for ($i = 0; $i < 5; $i++) {
        $this->breaker->recordFailure();
    }
    expect($this->breaker->getState())->toBe('OPEN');

    // Simulate cooldown period passing by creating a new breaker with zero cooldown
    $breaker = new CircuitBreaker($this->redis, failureThreshold: 5, cooldownSeconds: 0);
    expect($breaker->isAvailable())->toBeTrue();
    expect($breaker->getState())->toBe('HALF_OPEN');

    // Probe succeeds
    $breaker->recordSuccess();
    expect($breaker->getState())->toBe('CLOSED')
        ->and($breaker->isAvailable())->toBeTrue();
});

test('B3.6: HALF_OPEN probe failure reopens the circuit', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->breaker->recordFailure();
    }

    $breaker = new CircuitBreaker($this->redis, failureThreshold: 5, cooldownSeconds: 0);
    expect($breaker->isAvailable())->toBeTrue(); // HALF_OPEN

    $breaker->recordFailure();
    expect($breaker->getState())->toBe('OPEN')
        ->and($breaker->isAvailable())->toBeFalse();
});

test('retryAfterSeconds returns remaining cooldown time', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->breaker->recordFailure();
    }

    $retry = $this->breaker->retryAfterSeconds();
    expect($retry)->toBeGreaterThan(0)
        ->and($retry)->toBeLessThanOrEqual(30);
});

test('reset restores CLOSED state', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->breaker->recordFailure();
    }
    expect($this->breaker->getState())->toBe('OPEN');

    $this->breaker->reset();
    expect($this->breaker->getState())->toBe('CLOSED')
        ->and($this->breaker->getFailureCount())->toBe(0)
        ->and($this->breaker->isAvailable())->toBeTrue();
});

test('less than 5 failures does not open circuit', function () {
    $this->breaker->recordFailure();
    $this->breaker->recordFailure();
    $this->breaker->recordFailure();
    $this->breaker->recordFailure();

    expect($this->breaker->getState())->toBe('CLOSED')
        ->and($this->breaker->isAvailable())->toBeTrue()
        ->and($this->breaker->getFailureCount())->toBe(4);
});

test('circuit breaker state machine transitions', function () {
    // CLOSED -> OPEN
    for ($i = 0; $i < 5; $i++) {
        $this->breaker->recordFailure();
    }
    expect($this->breaker->getState())->toBe('OPEN');

    // OPEN -> HALF_OPEN (after cooldown)
    $breaker = new CircuitBreaker($this->redis, failureThreshold: 5, cooldownSeconds: 0);
    $breaker->isAvailable();
    expect($breaker->getState())->toBe('HALF_OPEN');

    // HALF_OPEN -> CLOSED (probe success)
    $breaker->recordSuccess();
    expect($breaker->getState())->toBe('CLOSED');
});
