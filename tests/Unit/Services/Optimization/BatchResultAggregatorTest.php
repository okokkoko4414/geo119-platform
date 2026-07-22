<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Optimization;

use App\Services\Optimization\BatchResultAggregator;
use App\Services\Optimization\BeforeAfterScore;
use App\Services\Optimization\OptimizationResult;
use App\Services\Optimization\OptimizationType;
use DateTimeImmutable;

test('aggregate computes summary statistics', function () {
    $results = [
        new OptimizationResult(
            id: '1', sourceText: 'one', optimizedText: 'one optimized',
            targetLocale: 'zh-CN', optimizationType: OptimizationType::Grammar,
            score: new BeforeAfterScore(0.7, 0.85, 0.214),
            costCents: 0.001, inputTokens: 10, outputTokens: 12,
            model: 'deepseek-chat', latencyMs: 500,
            cachedAt: new DateTimeImmutable,
        ),
        new OptimizationResult(
            id: '2', sourceText: 'two', optimizedText: 'two optimized',
            targetLocale: 'ja-JP', optimizationType: OptimizationType::Clarity,
            score: new BeforeAfterScore(0.6, 0.9, 0.5),
            costCents: 0.002, inputTokens: 8, outputTokens: 10,
            model: 'deepseek-chat', latencyMs: 300,
            cachedAt: new DateTimeImmutable,
        ),
    ];

    $aggregator = new BatchResultAggregator;
    $summary = $aggregator->aggregate($results);

    expect($summary['summary']['total'])->toBe(2)
        ->and($summary['summary']['successful'])->toBe(2)
        ->and($summary['summary']['failed'])->toBe(0)
        ->and($summary['summary']['total_cost_cents'])->toBe(0.003)
        ->and($summary['summary']['avg_latency_ms'])->toBe(400)
        ->and(count($summary['details']))->toBe(2)
        ->and(count($summary['failures']))->toBe(0);
});

test('aggregate handles mixed cache hits and misses', function () {
    $results = [
        new OptimizationResult(
            id: '1', sourceText: 'cached', optimizedText: 'cached result',
            targetLocale: 'vi-VN', optimizationType: OptimizationType::Full,
            score: new BeforeAfterScore(0.8, 0.9, 0.125),
            costCents: 0.0, inputTokens: 0, outputTokens: 0,
            model: 'deepseek-chat', latencyMs: 5,
            cachedAt: new DateTimeImmutable, fromCache: true,
        ),
        new OptimizationResult(
            id: '2', sourceText: 'fresh', optimizedText: 'fresh result',
            targetLocale: 'th-TH', optimizationType: OptimizationType::Tone,
            score: new BeforeAfterScore(0.7, 0.85, 0.214),
            costCents: 0.005, inputTokens: 20, outputTokens: 25,
            model: 'deepseek-chat', latencyMs: 800,
            cachedAt: new DateTimeImmutable, fromCache: false,
        ),
    ];

    $aggregator = new BatchResultAggregator;
    $summary = $aggregator->aggregate($results);

    expect($summary['summary']['cache_hits'])->toBe(1)
        ->and($summary['summary']['cache_hit_rate'])->toBe(0.5)
        ->and($summary['summary']['total_cost_cents'])->toBe(0.005);
});

test('aggregate includes all detail fields per item', function () {
    $result = new OptimizationResult(
        id: 'detail-1', sourceText: 'src', optimizedText: 'opt',
        targetLocale: 'ko-KR', optimizationType: OptimizationType::Fluency,
        score: new BeforeAfterScore(0.5, 0.75, 0.5),
        costCents: 0.003, inputTokens: 15, outputTokens: 18,
        model: 'deepseek-chat', latencyMs: 600,
        cachedAt: new DateTimeImmutable,
    );

    $aggregator = new BatchResultAggregator;
    $summary = $aggregator->aggregate([$result]);

    $detail = $summary['details'][0];
    expect($detail)->toHaveKey('id')
        ->and($detail)->toHaveKey('source_text')
        ->and($detail)->toHaveKey('optimized_text')
        ->and($detail)->toHaveKey('locale')
        ->and($detail)->toHaveKey('type')
        ->and($detail)->toHaveKey('before_score')
        ->and($detail)->toHaveKey('after_score')
        ->and($detail)->toHaveKey('improvement')
        ->and($detail)->toHaveKey('cost_cents')
        ->and($detail)->toHaveKey('latency_ms')
        ->and($detail)->toHaveKey('from_cache');
});

test('failureEntry creates structured failure record', function () {
    $entry = BatchResultAggregator::failureEntry(
        index: 3,
        sourceText: 'failed text',
        locale: 'de-DE',
        type: 'grammar',
        error: 'Connection timeout',
    );

    expect($entry['index'])->toBe(3)
        ->and($entry['source_text'])->toBe('failed text')
        ->and($entry['locale'])->toBe('de-DE')
        ->and($entry['type'])->toBe('grammar')
        ->and($entry['error'])->toBe('Connection timeout');
});

test('aggregate computes cost per word', function () {
    $result = new OptimizationResult(
        id: '1', sourceText: 'source', optimizedText: 'one two three four five',
        targetLocale: 'es-ES', optimizationType: OptimizationType::Grammar,
        score: new BeforeAfterScore(0.8, 0.9, 0.125),
        costCents: 0.010, inputTokens: 10, outputTokens: 12,
        model: 'deepseek-chat', latencyMs: 400,
        cachedAt: new DateTimeImmutable,
    );

    $aggregator = new BatchResultAggregator;
    $summary = $aggregator->aggregate([$result]);

    expect($summary['summary']['total_words'])->toBe(5)
        ->and($summary['summary']['cost_per_word_cents'])->toBe(0.01 / 5);
});
