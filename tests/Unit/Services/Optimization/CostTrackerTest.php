<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Optimization;

use App\Services\Optimization\CostTracker;
use App\Services\Optimization\DeepSeekResponse;
use Psr\Log\NullLogger;

beforeEach(function () {
    $this->redis = new FakeRedisStore;
    $this->logger = new NullLogger;
    $this->tracker = new CostTracker($this->redis, $this->logger, dailyBudgetCents: 10.0);
});

test('calculateCost computes correct cost for DeepSeek pricing', function () {
    $response = new DeepSeekResponse(
        optimizedText: 'test',
        inputTokens: 1_000_000,
        outputTokens: 1_000_000,
        model: 'deepseek-chat',
        latencyMs: 1000,
        sourceText: 'test',
        locale: 'zh-CN',
    );

    $cost = $this->tracker->calculateCost($response);

    // Input: 1M * $0.14/1M = $0.14 = 14 cents
    // Output: 1M * $0.28/1M = $0.28 = 28 cents
    // Total: 42 cents
    expect($cost)->toBe(42.0);
});

test('calculateCost handles zero tokens', function () {
    $response = new DeepSeekResponse(
        optimizedText: '',
        inputTokens: 0,
        outputTokens: 0,
        model: 'deepseek-chat',
        latencyMs: 0,
        sourceText: '',
        locale: 'en-US',
    );

    expect($this->tracker->calculateCost($response))->toBe(0.0);
});

test('record increments daily spend', function () {
    $response = new DeepSeekResponse(
        optimizedText: 'hello',
        inputTokens: 1000,
        outputTokens: 500,
        model: 'deepseek-chat',
        latencyMs: 200,
        sourceText: 'hello',
        locale: 'fr-FR',
    );

    $this->tracker->record($response, 'grammar');
    $spend = $this->tracker->getDailySpend();

    expect($spend)->toBeGreaterThan(0.0);
});

test('isWithinBudget returns true when under budget', function () {
    expect($this->tracker->isWithinBudget(5.0))->toBeTrue();
});

test('B3.10: isWithinBudget returns false when over budget', function () {
    // Spend $0.08 (8 cents)
    $response = new DeepSeekResponse(
        optimizedText: 'x',
        inputTokens: 500_000,
        outputTokens: 100_000,
        model: 'deepseek-chat',
        latencyMs: 100,
        sourceText: 'x',
        locale: 'en-US',
    );
    $this->tracker->record($response, 'grammar');

    $spend = $this->tracker->getDailySpend();
    expect($spend)->toBeGreaterThan(0.0);

    // Try to spend more than the remaining budget
    expect($this->tracker->isWithinBudget(500.0))->toBeFalse();
});

test('B3.10: daily budget cap enforcement', function () {
    $tinyBudget = new CostTracker($this->redis, $this->logger, dailyBudgetCents: 0.01);

    $response = new DeepSeekResponse(
        optimizedText: 'large',
        inputTokens: 1_000_000,
        outputTokens: 1_000_000,
        model: 'deepseek-chat',
        latencyMs: 1000,
        sourceText: 'large',
        locale: 'en-US',
    );

    $cost = $tinyBudget->calculateCost($response);
    expect($cost)->toBeGreaterThan(0.01); // Costs way more than budget
    expect($tinyBudget->isWithinBudget($cost))->toBeFalse();
});

test('costPerWord calculates correctly', function () {
    $cost = $this->tracker->costPerWord(10000, 5.0);
    expect($cost)->toBe(0.0005);
});

test('costPerWord handles zero words', function () {
    expect($this->tracker->costPerWord(0, 5.0))->toBe(0.0);
});

test('resetDailySpend zeros out daily spend', function () {
    $response = new DeepSeekResponse(
        optimizedText: 'test',
        inputTokens: 1000,
        outputTokens: 500,
        model: 'deepseek-chat',
        latencyMs: 100,
        sourceText: 'test',
        locale: 'en-US',
    );
    $this->tracker->record($response, 'grammar');
    expect($this->tracker->getDailySpend())->toBeGreaterThan(0.0);

    $this->tracker->resetDailySpend();
    expect($this->tracker->getDailySpend())->toBe(0.0);
});

test('CostTracker B3.2: cost per word stays below $0.001 target', function () {
    $cost = $this->tracker->costPerWord(
        totalWords: 100_000,
        totalCostCents: 50.0, // $0.50 for 100k words
    );

    // $0.50 / 100,000 = $0.000005 per word = 0.0005 cents per word
    // We need < $0.001, so < 0.1 cents per word
    expect($cost)->toBeLessThan(0.1);
});
