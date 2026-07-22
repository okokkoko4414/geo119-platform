<?php

declare(strict_types=1);

namespace Tests\Unit\Services\ClaudeLocal;

use App\Services\ClaudeLocal\CircuitBreaker;
use App\Services\ClaudeLocal\CircuitBreakerOpenException;
use App\Services\ClaudeLocal\RateLimitExceededException;

beforeEach(function () {
    $this->breaker = new CircuitBreaker(failureThreshold: 5, openDurationSeconds: 30);
});

test('initial state is closed', function () {
    expect($this->breaker->getState())->toBe('closed')
        ->and($this->breaker->isOpen())->toBeFalse()
        ->and($this->breaker->getFailureCount())->toBe(0);
});

test('opens after threshold failures', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->breaker->recordFailure();
    }

    expect($this->breaker->getState())->toBe('open')
        ->and($this->breaker->isOpen())->toBeTrue();
});

test('success resets failure count', function () {
    $this->breaker->recordFailure();
    $this->breaker->recordFailure();
    expect($this->breaker->getFailureCount())->toBe(2);

    $this->breaker->recordSuccess();

    expect($this->breaker->getState())->toBe('closed')
        ->and($this->breaker->getFailureCount())->toBe(0);
});

test('stays closed below threshold', function () {
    for ($i = 0; $i < 4; $i++) {
        $this->breaker->recordFailure();
    }

    expect($this->breaker->getState())->toBe('closed')
        ->and($this->breaker->isOpen())->toBeFalse()
        ->and($this->breaker->getFailureCount())->toBe(4);
});

test('half-open transition after cooldown', function () {
    $breaker = new CircuitBreaker(failureThreshold: 5, openDurationSeconds: 1);

    for ($i = 0; $i < 5; $i++) {
        $breaker->recordFailure();
    }
    expect($breaker->getState())->toBe('open');
    expect($breaker->isOpen())->toBeTrue();

    // Wait for cooldown to expire so the circuit transitions to half-open
    usleep(1_100_000); // 1.1s

    expect($breaker->isOpen())->toBeFalse();
    expect($breaker->getState())->toBe('half-open');

    // Half-open: success resets to closed
    $breaker->recordSuccess();
    expect($breaker->getState())->toBe('closed');
});

test('half-open success returns to closed', function () {
    $breaker = new CircuitBreaker(failureThreshold: 5, openDurationSeconds: 1);

    for ($i = 0; $i < 5; $i++) {
        $breaker->recordFailure();
    }
    expect($breaker->getState())->toBe('open');

    // Wait for half-open
    usleep(1_100_000);
    expect($breaker->getState())->toBe('half-open');

    $breaker->recordSuccess();
    expect($breaker->getState())->toBe('closed');
});

test('half-open failure reopens circuit', function () {
    $breaker = new CircuitBreaker(failureThreshold: 5, openDurationSeconds: 1);

    for ($i = 0; $i < 5; $i++) {
        $breaker->recordFailure();
    }
    expect($breaker->getState())->toBe('open');

    // Wait for half-open
    usleep(1_100_000);
    expect($breaker->getState())->toBe('half-open');

    // Probe failure -> reopens immediately
    $breaker->recordFailure();
    expect($breaker->getState())->toBe('open');
    expect($breaker->isOpen())->toBeTrue();
});

test('exception classes exist', function () {
    expect(class_exists(CircuitBreakerOpenException::class))->toBeTrue();
    expect(class_exists(RateLimitExceededException::class))->toBeTrue();
});
