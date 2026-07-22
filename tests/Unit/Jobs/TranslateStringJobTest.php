<?php

declare(strict_types=1);

use App\Jobs\TranslateStringJob;
use App\Models\Translation;
use App\Services\ClaudeLocal\ClaudeLocalClient;
use App\Services\LanguageRegistry;
use App\Services\QualityGate;
use App\Services\TranslationCache;
use App\Services\TranslationManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('languages.languages', [
        ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'tier' => 1],
        ['code' => 'vi', 'name' => 'Vietnamese', 'native_name' => 'Tiếng Việt', 'tier' => 1],
    ]);
    Config::set('languages.baseline_languages', ['en', 'vi']);
});

it('skips when translation is already cached', function (): void {
    $cache = mock(TranslationCache::class);
    $cache->shouldReceive('has')->with('vi', 'ui', 'hello')->once()->andReturn(true);
    $cache->shouldNotReceive('warm');

    $ai = mock(ClaudeLocalClient::class);
    $ai->shouldNotReceive('translate');

    $job = new TranslateStringJob('vi', 'ui', 'hello', 'Hello', 1);
    $job->handle($ai, $cache, app(QualityGate::class), app(TranslationManager::class));

    expect(true)->toBeTrue();
});

it('translates, scores, persists, and warms cache', function (): void {
    $ai = mock(ClaudeLocalClient::class);
    $ai->shouldReceive('translate')
        ->once()
        ->with('Hello', 'vi', Mockery::any())
        ->andReturn(['content' => 'Xin chao']);

    $ai->shouldReceive('chat')
        ->once()
        ->andReturn(['content' => '0.92']);

    $cache = mock(TranslationCache::class);
    $cache->shouldReceive('has')->with('vi', 'ui', 'hello')->once()->andReturn(false);
    $cache->shouldReceive('warm')->with('vi', 'ui', 'hello', 'Xin chao')->once();

    $manager = app(TranslationManager::class);
    $quality = new QualityGate(app(LanguageRegistry::class), $ai);

    $job = new TranslateStringJob('vi', 'ui', 'hello', 'Hello', 1);
    $job->handle($ai, $cache, $quality, $manager);

    $translation = Translation::where('locale', 'vi')
        ->where('namespace', 'ui')
        ->where('key', 'hello')
        ->first();

    expect($translation)->not->toBeNull()
        ->and($translation->value)->toBe('Xin chao')
        ->and($translation->is_machine_translated)->toBeTrue()
        ->and($translation->quality_score)->toBe(0.92);
});

it('retries on AI failure without persisting', function (): void {
    $ai = mock(ClaudeLocalClient::class);
    $ai->shouldReceive('translate')
        ->once()
        ->andThrow(new RuntimeException('timeout'));

    $cache = mock(TranslationCache::class);
    $cache->shouldReceive('has')->with('vi', 'ui', 'hello')->once()->andReturn(false);
    $cache->shouldNotReceive('warm');

    $job = new TranslateStringJob('vi', 'ui', 'hello', 'Hello', 1);
    $job->handle($ai, $cache, app(QualityGate::class), app(TranslationManager::class));

    // On non-final attempt, no translation should be persisted
    expect(Translation::count())->toBe(0);
});

it('persists fallback on final attempt failure', function (): void {
    $ai = mock(ClaudeLocalClient::class);
    $ai->shouldReceive('translate')
        ->once()
        ->andThrow(new RuntimeException('timeout'));

    $cache = mock(TranslationCache::class);
    $cache->shouldReceive('has')->with('vi', 'ui', 'hello')->once()->andReturn(false);

    $manager = app(TranslationManager::class);
    $quality = app(QualityGate::class);

    $job = new TranslateStringJob('vi', 'ui', 'hello', 'Hello', 1);
    $job->tries = 1;

    $job->handle($ai, $cache, $quality, $manager);

    $translation = Translation::where('locale', 'vi')
        ->where('key', 'hello')
        ->first();
    expect($translation)->not->toBeNull()
        ->and($translation->is_machine_translated)->toBeFalse();
});

it('has correct tags for monitoring', function (): void {
    $job = new TranslateStringJob('vi', 'ui', 'hello', 'Hello', 1);

    expect($job->tags())->toContain('locale:vi')
        ->and($job->tags())->toContain('tier:1')
        ->and($job->tags())->toContain('namespace:ui');
});
