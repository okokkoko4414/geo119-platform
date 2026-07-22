<?php

declare(strict_types=1);

namespace Tests\Unit\Services\ClaudeLocal;

use App\Services\ClaudeLocal\CircuitBreaker;
use App\Services\ClaudeLocal\CircuitBreakerOpenException;
use App\Services\ClaudeLocal\RateLimitExceededException;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    $this->breaker = new CircuitBreaker('test', failureThreshold: 5, openDurationSeconds: 30);
});

afterEach(function () {
    Redis::connection('cache')->del(
        'circuit_breaker:test:failures',
        'circuit_breaker:test:opened_at',
    );
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
    $breaker = new CircuitBreaker('halfopen', failureThreshold: 5, openDurationSeconds: 1);

    for ($i = 0; $i < 5; $i++) {
        $breaker->recordFailure();
    }
    expect($breaker->getState())->toBe('open');
    expect($breaker->isOpen())->toBeTrue();

    usleep(1_100_000);

    expect($breaker->isOpen())->toBeFalse();
    expect($breaker->getState())->toBe('half-open');

    $breaker->recordSuccess();
    expect($breaker->getState())->toBe('closed');

    Redis::connection('cache')->del(
        'circuit_breaker:halfopen:failures',
        'circuit_breaker:halfopen:opened_at',
    );
});

test('half-open success returns to closed', function () {
    $breaker = new CircuitBreaker('halfopen2', failureThreshold: 5, openDurationSeconds: 1);

    for ($i = 0; $i < 5; $i++) {
        $breaker->recordFailure();
    }
    expect($breaker->getState())->toBe('open');

    usleep(1_100_000);
    expect($breaker->getState())->toBe('half-open');

    $breaker->recordSuccess();
    expect($breaker->getState())->toBe('closed');

    Redis::connection('cache')->del(
        'circuit_breaker:halfopen2:failures',
        'circuit_breaker:halfopen2:opened_at',
    );
});

test('half-open failure reopens circuit', function () {
    $breaker = new CircuitBreaker('halfopen3', failureThreshold: 5, openDurationSeconds: 1);

    for ($i = 0; $i < 5; $i++) {
        $breaker->recordFailure();
    }
    expect($breaker->getState())->toBe('open');

    usleep(1_100_000);
    expect($breaker->getState())->toBe('half-open');

    $breaker->recordFailure();
    expect($breaker->getState())->toBe('open');
    expect($breaker->isOpen())->toBeTrue();

    Redis::connection('cache')->del(
        'circuit_breaker:halfopen3:failures',
        'circuit_breaker:halfopen3:opened_at',
    );
});

test('exception classes exist', function () {
    expect(class_exists(CircuitBreakerOpenException::class))->toBeTrue();
    expect(class_exists(RateLimitExceededException::class))->toBeTrue();
});
