<?php

declare(strict_types=1);

namespace Tests\Unit\Services\ClaudeLocal;

use App\Services\ClaudeLocal\CostTracker;

beforeEach(function () {
    $this->tracker = new CostTracker;
});

test('record stores request and calculates cost', function () {
    $this->tracker->record('deepseek-chat', 1_000_000, 1_000_000, 1000);

    $summary = $this->tracker->getSummary();

    expect($summary['total_requests'])->toBe(1)
        ->and($summary['total_input_tokens'])->toBe(1_000_000)
        ->and($summary['total_output_tokens'])->toBe(1_000_000)
        ->and($summary['total_cost_cents'])->toBeGreaterThan(0)
        ->and($summary['total_latency_ms'])->toBe(1000);
});

test('record handles zero tokens', function () {
    $this->tracker->record('deepseek-chat', 0, 0, 0);

    $summary = $this->tracker->getSummary();

    expect($summary['total_requests'])->toBe(1)
        ->and($summary['total_input_tokens'])->toBe(0)
        ->and($summary['total_output_tokens'])->toBe(0)
        ->and($summary['total_cost_cents'])->toBe(0)
        ->and($summary['total_latency_ms'])->toBe(0);
});

test('cumulative summary across multiple records', function () {
    $this->tracker->record('deepseek-chat', 1000, 500, 200);
    $this->tracker->record('deepseek-chat', 2000, 1000, 400);

    $summary = $this->tracker->getSummary();

    expect($summary['total_requests'])->toBe(2)
        ->and($summary['total_input_tokens'])->toBe(3000)
        ->and($summary['total_output_tokens'])->toBe(1500)
        ->and($summary['total_latency_ms'])->toBe(600);
});

test('getRecentRequests returns last N records', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->tracker->record('deepseek-chat', 100, 50, 100);
    }

    $recent = $this->tracker->getRecentRequests(3);
    expect($recent)->toHaveCount(3);
});

test('getRecentRequests returns all when less than limit', function () {
    $this->tracker->record('deepseek-chat', 100, 50, 100);

    $recent = $this->tracker->getRecentRequests(20);
    expect($recent)->toHaveCount(1);
});

test('avg latency computed correctly', function () {
    $this->tracker->record('deepseek-chat', 100, 50, 100);
    $this->tracker->record('deepseek-chat', 100, 50, 300);

    $summary = $this->tracker->getSummary();
    expect($summary['avg_latency_ms'])->toBe(200);
});

test('empty tracker has zero avg latency', function () {
    $summary = $this->tracker->getSummary();
    expect($summary['avg_latency_ms'])->toBe(0)
        ->and($summary['total_requests'])->toBe(0);
});

test('pricing calculation is reasonable', function () {
    // 1M input tokens at $0.14/1M = 14 cents
    $this->tracker->record('deepseek-chat', 1_000_000, 0, 100);

    $summary = $this->tracker->getSummary();
    expect($summary['total_cost_cents'])->toBe(14);
});

test('output tokens priced higher than input', function () {
    // 1M output tokens at $0.28/1M = 28 cents
    $this->tracker->record('deepseek-chat', 0, 1_000_000, 100);

    $summary = $this->tracker->getSummary();
    expect($summary['total_cost_cents'])->toBe(28);
});
