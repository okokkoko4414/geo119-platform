<?php

declare(strict_types=1);

use App\Models\Translation;
use App\Services\TranslationCache;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores and retrieves translations from cache', function (): void {
    $cache = app(TranslationCache::class);

    $cache->put('vi', 'ui', 'hello', 'xin chao');

    expect($cache->get('vi', 'ui', 'hello'))->toBe('xin chao');
});

it('returns null for missing cache entries', function (): void {
    $cache = app(TranslationCache::class);

    expect($cache->get('xx', 'ui', 'missing'))->toBeNull();
});

it('checks existence with has()', function (): void {
    $cache = app(TranslationCache::class);

    $cache->put('vi', 'ui', 'hello', 'xin chao');

    expect($cache->has('vi', 'ui', 'hello'))->toBeTrue()
        ->and($cache->has('vi', 'ui', 'nonexistent'))->toBeFalse();
});

it('forgets individual keys', function (): void {
    $cache = app(TranslationCache::class);
    $cache->put('vi', 'ui', 'hello', 'xin chao');
    $cache->forget('vi', 'ui', 'hello');

    expect($cache->has('vi', 'ui', 'hello'))->toBeFalse();
});

it('forgets entire locale', function (): void {
    $cache = app(TranslationCache::class);
    $cache->put('vi', 'ui', 'key1', 'val1');
    $cache->put('vi', 'ui', 'key2', 'val2');
    $cache->put('en', 'ui', 'key1', 'val1');

    $cache->forgetLocale('vi');

    expect($cache->has('vi', 'ui', 'key1'))->toBeFalse()
        ->and($cache->has('vi', 'ui', 'key2'))->toBeFalse()
        ->and($cache->has('en', 'ui', 'key1'))->toBeTrue();
});

it('warms cache from database', function (): void {
    Translation::create([
        'locale' => 'vi', 'namespace' => 'ui', 'key' => 'hello',
        'value' => 'xin chao', 'source_value' => 'hello', 'quality_score' => 0.90,
    ]);
    Translation::create([
        'locale' => 'vi', 'namespace' => 'ui', 'key' => 'goodbye',
        'value' => 'tam biet', 'source_value' => 'goodbye', 'quality_score' => 0.88,
    ]);

    $cache = app(TranslationCache::class);
    $count = $cache->warmFromDatabase('vi');

    expect($count)->toBe(2)
        ->and($cache->get('vi', 'ui', 'hello'))->toBe('xin chao')
        ->and($cache->get('vi', 'ui', 'goodbye'))->toBe('tam biet');
});

it('handles warm for locale with no translations', function (): void {
    $cache = app(TranslationCache::class);
    $count = $cache->warmFromDatabase('xx');

    expect($count)->toBe(0);
});

it('has distinct cache keys per locale and namespace', function (): void {
    $cache = app(TranslationCache::class);

    $cache->put('vi', 'ui', 'key', 'vietnamese-ui');
    $cache->put('vi', 'errors', 'key', 'vietnamese-errors');
    $cache->put('en', 'ui', 'key', 'english-ui');

    expect($cache->get('vi', 'ui', 'key'))->toBe('vietnamese-ui')
        ->and($cache->get('vi', 'errors', 'key'))->toBe('vietnamese-errors')
        ->and($cache->get('en', 'ui', 'key'))->toBe('english-ui');
});
