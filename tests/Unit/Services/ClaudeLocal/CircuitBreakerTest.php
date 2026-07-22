<?php

declare(strict_types=1);

namespace Tests\Unit\Services\ClaudeLocal;

use App\Services\ClaudeLocal\CircuitBreaker;

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
    for ($i = 0; $i < 5; $i++) {
        $this->breaker->recordFailure();
    }
    expect($this->breaker->getState())->toBe('open');

    $fastBreaker = new CircuitBreaker(failureThreshold: 5, openDurationSeconds: 0);
    expect($fastBreaker->isOpen())->toBeFalse();
    expect($fastBreaker->getState())->toBe('half-open');
});

test('half-open success returns to closed', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->breaker->recordFailure();
    }

    $fastBreaker = new CircuitBreaker(failureThreshold: 5, openDurationSeconds: 0);
    $fastBreaker->isOpen();
    expect($fastBreaker->getState())->toBe('half-open');

    $fastBreaker->recordSuccess();
    expect($fastBreaker->getState())->toBe('closed');
});

test('half-open failure reopens circuit', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->breaker->recordFailure();
    }

    $fastBreaker = new CircuitBreaker(failureThreshold: 5, openDurationSeconds: 0);
    $fastBreaker->isOpen();
    expect($fastBreaker->getState())->toBe('half-open');

    $fastBreaker->recordFailure();
    expect($fastBreaker->getState())->toBe('open');
});

test('exception classes exist', function () {
    expect(class_exists(\App\Services\ClaudeLocal\CircuitBreakerOpenException::class))->toBeTrue();
    expect(class_exists(\App\Services\ClaudeLocal\RateLimitExceededException::class))->toBeTrue();
});
