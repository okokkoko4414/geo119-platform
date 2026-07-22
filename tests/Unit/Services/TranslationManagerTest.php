<?php

declare(strict_types=1);

use App\Jobs\TranslateStringJob;
use App\Models\Language;
use App\Models\Translation;
use App\Services\TranslationManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('languages.languages', [
        ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'tier' => 1],
        ['code' => 'vi', 'name' => 'Vietnamese', 'native_name' => 'Tiếng Việt', 'tier' => 1],
        ['code' => 'th', 'name' => 'Thai', 'native_name' => 'ไทย', 'tier' => 2],
        ['code' => 'lo', 'name' => 'Lao', 'native_name' => 'ລາວ', 'tier' => 3],
    ]);
    Config::set('languages.tiers', [
        1 => ['threshold' => 0.85, 'label' => 'Premium'],
        2 => ['threshold' => 0.68, 'label' => 'Beta'],
        3 => ['threshold' => 0.70, 'label' => 'Community'],
    ]);
    Config::set('languages.baseline_languages', ['en', 'vi']);
});

it('expands a language by dispatching translation jobs', function (): void {
    Bus::fake();

    $manager = app(TranslationManager::class);
    $strings = [
        'hello' => 'Hello',
        'goodbye' => 'Goodbye',
    ];

    $dispatched = $manager->expandLanguage('vi', $strings);

    expect($dispatched)->toBe(2);
    Bus::assertDispatched(TranslateStringJob::class, 2);
    Bus::assertDispatched(TranslateStringJob::class, fn ($job): bool =>
        $job->locale === 'vi' && $job->key === 'hello'
    );
});

it('dispatches jobs to correct tier queues', function (): void {
    Bus::fake();

    $manager = app(TranslationManager::class);

    $manager->expandLanguage('vi', ['key' => 'value']);    // Tier 1
    $manager->expandLanguage('th', ['key' => 'value']);    // Tier 2
    $manager->expandLanguage('lo', ['key' => 'value']);    // Tier 3

    Bus::assertDispatched(TranslateStringJob::class, 3);

    $jobs = Bus::dispatched(TranslateStringJob::class);
    $queues = $jobs->map(fn ($job) => $job[0]['job']->queue ?? null)->filter();

    expect($queues)->toContain('translations-tier1')
        ->and($queues)->toContain('translations-tier2')
        ->and($queues)->toContain('translations-tier3');
});

it('extracts and reinserts placeholders', function (): void {
    $manager = app(TranslationManager::class);

    $value = 'Hello <strong>:name</strong>, you have {count} messages';
    [$cleaned, $placeholders] = $manager->preprocessValue($value);

    expect($cleaned)->not->toContain('<strong>')
        ->and($cleaned)->not->toContain(':name')
        ->and($cleaned)->not->toContain('{count}');

    $reinserted = $manager->reinsertPlaceholders($cleaned, $placeholders);
    expect($reinserted)->toBe($value);
});

it('extracts HTML tags as placeholders', function (): void {
    $manager = app(TranslationManager::class);

    $value = '<a href="/login">Sign in</a> or <a href="/signup">Sign up</a>';
    [$cleaned, $placeholders] = $manager->preprocessValue($value);

    expect($cleaned)->not->toContain('<a')
        ->and($cleaned)->not->toContain('href');

    $reinserted = $manager->reinsertPlaceholders($cleaned, $placeholders);
    expect($reinserted)->toBe($value);
});

it('extracts ICU message format placeholders', function (): void {
    $manager = app(TranslationManager::class);

    $value = '{count, plural, =1 {1 item} other {# items}} in cart';
    [$cleaned, $placeholders] = $manager->preprocessValue($value);

    expect($cleaned)->not->toContain('{count');

    $reinserted = $manager->reinsertPlaceholders($cleaned, $placeholders);
    expect($reinserted)->toBe($value);
});

it('validates placeholder integrity', function (): void {
    $manager = app(TranslationManager::class);

    $original = 'Hello :name';
    [$cleaned, $placeholders] = $manager->preprocessValue($original);
    $translated = 'Xin chao :name'; // Placeholder preserved

    expect($manager->validatePlaceholders($original, $translated, $placeholders))->toBeTrue();
});

it('detects broken placeholders in translation', function (): void {
    $manager = app(TranslationManager::class);

    $original = 'Hello :name';
    [$cleaned, $placeholders] = $manager->preprocessValue($original);
    $translated = 'Xin chao'; // Placeholder LOST

    expect($manager->validatePlaceholders($original, $translated, $placeholders))->toBeFalse();
});

it('retranslates a key', function (): void {
    Bus::fake();
    $manager = app(TranslationManager::class);

    // Pre-seed the source (English) text
    Language::create([
        'code' => 'vi', 'name' => 'Vietnamese', 'tier' => 1, 'is_active' => true,
    ]);

    Translation::create([
        'locale' => 'en', 'namespace' => 'ui', 'key' => 'hello',
        'value' => 'Hello', 'source_value' => 'Hello', 'quality_score' => 1.0,
    ]);

    $manager->retranslateKey('vi', 'ui', 'hello', 'Hello');

    Bus::assertDispatched(TranslateStringJob::class, 1);
});

it('retranslates an entire locale', function (): void {
    Bus::fake();

    Language::create([
        'code' => 'vi', 'name' => 'Vietnamese', 'tier' => 1, 'is_active' => true,
    ]);

    Translation::create([
        'locale' => 'en', 'namespace' => 'ui', 'key' => 'hello',
        'value' => 'Hello', 'source_value' => 'Hello', 'quality_score' => 1.0,
    ]);
    Translation::create([
        'locale' => 'en', 'namespace' => 'ui', 'key' => 'goodbye',
        'value' => 'Goodbye', 'source_value' => 'Goodbye', 'quality_score' => 1.0,
    ]);

    $manager = app(TranslationManager::class);
    $dispatched = $manager->retranslateLocale('vi');

    expect($dispatched)->toBe(2);
    Bus::assertDispatched(TranslateStringJob::class, 2);
});
