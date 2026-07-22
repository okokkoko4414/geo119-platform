<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Optimization;

use App\Services\Optimization\BeforeAfterScore;
use App\Services\Optimization\DedupCache;
use App\Services\Optimization\OptimizationResult;
use App\Services\Optimization\OptimizationType;
use DateTimeImmutable;

beforeEach(function () {
    $this->redis = new FakeRedisStore;
    $this->cache = new DedupCache($this->redis);
});

test('get returns null for uncached key', function () {
    $result = $this->cache->get('unseen text', 'zh-CN', OptimizationType::Grammar);
    expect($result)->toBeNull();
});

test('set and get round-trips an OptimizationResult', function () {
    $result = new OptimizationResult(
        id: 'test-1',
        sourceText: 'Hello world',
        optimizedText: 'Hello optimized world',
        targetLocale: 'zh-CN',
        optimizationType: OptimizationType::Grammar,
        score: new BeforeAfterScore(0.8, 0.9, 0.125),
        costCents: 0.001,
        inputTokens: 10,
        outputTokens: 12,
        model: 'deepseek-chat',
        latencyMs: 500,
        cachedAt: new DateTimeImmutable,
    );

    $this->cache->set('Hello world', 'zh-CN', OptimizationType::Grammar, $result);
    $cached = $this->cache->get('Hello world', 'zh-CN', OptimizationType::Grammar);

    expect($cached)->not->toBeNull()
        ->and($cached->id)->toBe('test-1')
        ->and($cached->optimizedText)->toBe('Hello optimized world')
        ->and($cached->score->beforeScore)->toBe(0.8)
        ->and($cached->score->afterScore)->toBe(0.9)
        ->and($cached->costCents)->toBe(0.001);
});

test('different inputs produce different cache keys', function () {
    $key1 = $this->cache->hashKey('text A', 'zh-CN', OptimizationType::Grammar);
    $key2 = $this->cache->hashKey('text B', 'zh-CN', OptimizationType::Grammar);
    $key3 = $this->cache->hashKey('text A', 'ja-JP', OptimizationType::Grammar);
    $key4 = $this->cache->hashKey('text A', 'zh-CN', OptimizationType::Full);

    expect($key1)->not->toBe($key2)
        ->and($key1)->not->toBe($key3)
        ->and($key1)->not->toBe($key4);
});

test('identical inputs produce same cache key', function () {
    $key1 = $this->cache->hashKey('same text', 'zh-CN', OptimizationType::Grammar);
    $key2 = $this->cache->hashKey('same text', 'zh-CN', OptimizationType::Grammar);

    expect($key1)->toBe($key2);
});

test('acquireLock returns true on first call and false on second', function () {
    $first = $this->cache->acquireLock('text', 'zh-CN', OptimizationType::Grammar);
    $second = $this->cache->acquireLock('text', 'zh-CN', OptimizationType::Grammar);

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse();
});

test('acquireLock returns true after release', function () {
    $this->cache->acquireLock('text', 'zh-CN', OptimizationType::Grammar);
    $this->cache->releaseLock('text', 'zh-CN', OptimizationType::Grammar);

    $again = $this->cache->acquireLock('text', 'zh-CN', OptimizationType::Grammar);
    expect($again)->toBeTrue();
});

test('pollForResult returns cached result when available', function () {
    $result = new OptimizationResult(
        id: 'poll-test',
        sourceText: 'poll text',
        optimizedText: 'poll optimized',
        targetLocale: 'zh-CN',
        optimizationType: OptimizationType::Grammar,
        score: new BeforeAfterScore(0.7, 0.85, 0.214),
        costCents: 0.002,
        inputTokens: 5,
        outputTokens: 6,
        model: 'deepseek-chat',
        latencyMs: 300,
        cachedAt: new DateTimeImmutable,
    );

    // Simulate: another process computes and caches the result
    $this->cache->set('poll text', 'zh-CN', OptimizationType::Grammar, $result);

    $polled = $this->cache->pollForResult('poll text', 'zh-CN', OptimizationType::Grammar, 1000);
    expect($polled)->not->toBeNull()
        ->and($polled->id)->toBe('poll-test');
});

test('pollForResult returns null when lock expires without result', function () {
    $polled = $this->cache->pollForResult('no-result', 'zh-CN', OptimizationType::Grammar, 200);
    expect($polled)->toBeNull();
});

test('DedupCache B3.4: identical input returns cached result', function () {
    $result = new OptimizationResult(
        id: 'dedup-b3-4',
        sourceText: 'Duplicate test text',
        optimizedText: 'Optimized once',
        targetLocale: 'vi-VN',
        optimizationType: OptimizationType::Full,
        score: new BeforeAfterScore(0.6, 0.9, 0.5),
        costCents: 0.005,
        inputTokens: 20,
        outputTokens: 22,
        model: 'deepseek-chat',
        latencyMs: 800,
        cachedAt: new DateTimeImmutable,
    );

    $this->cache->set('Duplicate test text', 'vi-VN', OptimizationType::Full, $result);

    $first = $this->cache->get('Duplicate test text', 'vi-VN', OptimizationType::Full);
    $second = $this->cache->get('Duplicate test text', 'vi-VN', OptimizationType::Full);

    expect($first)->not->toBeNull()
        ->and($second)->not->toBeNull()
        ->and($first->id)->toBe($second->id)
        ->and($first->costCents)->toBe($second->costCents)
        ->and($first->optimizedText)->toBe($second->optimizedText);
});

test('cache key includes dedup prefix and SHA256 hash', function () {
    $key = $this->cache->cacheKey('test', 'zh-CN', OptimizationType::Grammar);
    expect($key)->toStartWith('dedup:')
        ->and(strlen($key))->toBe(6 + 64); // 'dedup:' + SHA256 hex
});

test('hash is deterministic for identical inputs', function () {
    $hash1 = $this->cache->hashKey('Hello', 'en-US', OptimizationType::Grammar);
    $hash2 = $this->cache->hashKey('Hello', 'en-US', OptimizationType::Grammar);
    expect($hash1)->toBe($hash2)
        ->and(strlen($hash1))->toBe(64); // SHA256 produces 64 hex chars
});
