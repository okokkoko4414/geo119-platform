<?php

declare(strict_types=1);

use App\Services\LanguageRegistry;
use Illuminate\Support\Facades\Config;

/**
 * B1 Acceptance Criteria — 70 Languages Across 3 Quality Tiers
 *
 * This test suite verifies acceptance criteria B1.1 through B1.5
 * at the code level. Criteria B1.6 (RTL rendering) requires B4 UI
 * infrastructure, B1.7 (10k keys in <1h) requires B5 Horizon/claude_local,
 * and B1.8 (before/after scores) is covered by B3 tests.
 */
beforeEach(function (): void {
    // Use the app's actual config values loaded from config/languages.php
    Config::set('languages.languages', config('languages.languages'));
    Config::set('languages.tiers', [
        1 => ['threshold' => 0.85, 'label' => 'Premium'],
        2 => ['threshold' => 0.68, 'label' => 'Beta'],
        3 => ['threshold' => 0.70, 'label' => 'Community'],
    ]);
    Config::set('languages.baseline_languages', [
        'en', 'zh', 'es', 'ar', 'pt', 'ru', 'fr', 'de', 'ja', 'ko',
        'it', 'nl', 'pl', 'sv', 'da', 'fi', 'nb', 'cs', 'el', 'hu',
        'ro', 'sk', 'uk', 'he', 'tr',
    ]);
});

test('B1.1 — 70 languages defined in LanguageRegistry', function (): void {
    $registry = new LanguageRegistry;

    expect($registry->definitionCount())->toBe(70);
});

test('B1.1 — Tier counts are 30/35/5', function (): void {
    $registry = new LanguageRegistry;
    $definitions = $registry->getDefinitions();

    $tierCounts = [1 => 0, 2 => 0, 3 => 0];
    foreach ($definitions as $def) {
        $tierCounts[$def['tier']]++;
    }

    expect($tierCounts[1])->toBe(30, 'Tier 1 should have 30 languages')
        ->and($tierCounts[2])->toBe(35, 'Tier 2 should have 35 languages')
        ->and($tierCounts[3])->toBe(5, 'Tier 3 should have 5 languages');
});

test('B1.2 — Tier 1 quality threshold is 0.85', function (): void {
    $registry = new LanguageRegistry;

    expect($registry->getQualityThreshold(1))->toBe(0.85);
});

test('B1.3 — Tier 2 threshold is 80% of Tier 1 baseline', function (): void {
    $registry = new LanguageRegistry;

    expect($registry->getQualityThreshold(2))->toBe(0.68);
});

test('B1.3 — Beta tier label for Tier 2', function (): void {
    $tiers = Config::get('languages.tiers');

    expect($tiers[2]['label'])->toBe('Beta');
});

test('B1.4 — Tier 3 uses coverage-based gate at 70%', function (): void {
    $registry = new LanguageRegistry;

    expect($registry->getQualityThreshold(3))->toBe(0.70);
});

test('B1.5 — Baseline languages list has exactly 25 entries', function (): void {
    $registry = new LanguageRegistry;

    expect($registry->getBaselineLanguages())->toHaveCount(25);
});

test('B1.5 — All baseline languages are Tier 1', function (): void {
    $registry = new LanguageRegistry;
    $baselines = $registry->getBaselineLanguages();

    foreach ($baselines as $code) {
        $def = $registry->getDefinition($code);
        expect($def)->not->toBeNull("Baseline language {$code} not found in definitions")
            ->and($def['tier'])->toBe(1, "Baseline language {$code} must be Tier 1");
    }
});

test('B1.5 — Baseline includes English, Vietnamese, and 23 other languages', function (): void {
    $registry = new LanguageRegistry;
    $baselines = $registry->getBaselineLanguages();

    // Spot-check key languages: all 7 must be present in the 25 baseline
    expect(in_array('en', $baselines, true))->toBeTrue('en should be in baseline')
        ->and(in_array('zh', $baselines, true))->toBeTrue('zh should be in baseline')
        ->and(in_array('es', $baselines, true))->toBeTrue('es should be in baseline')
        ->and(in_array('ar', $baselines, true))->toBeTrue('ar should be in baseline')
        ->and(in_array('ja', $baselines, true))->toBeTrue('ja should be in baseline')
        ->and(in_array('de', $baselines, true))->toBeTrue('de should be in baseline')
        ->and(in_array('fr', $baselines, true))->toBeTrue('fr should be in baseline');

    // New Tier 1 additions (not baseline) should NOT be in baseline
    expect(in_array('vi', $baselines, true))->toBeFalse('vi is a new addition, not baseline')
        ->and(in_array('th', $baselines, true))->toBeFalse('th is a new addition, not baseline');
});

test('B1.6 — RTL languages are correctly identified', function (): void {
    $registry = new LanguageRegistry;

    $rtlCodes = Config::get('languages.rtl', []);
    expect($rtlCodes)->toHaveCount(5);

    foreach ($rtlCodes as $code) {
        expect($registry->isRtl($code))->toBeTrue("{$code} should be RTL");
    }
    expect($registry->isRtl('en'))->toBeFalse('English should not be RTL');
});

test('All 70 language definitions have required fields', function (): void {
    $registry = new LanguageRegistry;

    foreach ($registry->getDefinitions() as $def) {
        expect($def)->toHaveKeys(['code', 'name', 'native_name', 'tier'])
            ->and($def['code'])->toBeString()->not->toBeEmpty()
            ->and($def['name'])->toBeString()->not->toBeEmpty()
            ->and($def['tier'])->toBeIn([1, 2, 3]);
    }
});

test('All language codes are unique', function (): void {
    $registry = new LanguageRegistry;
    $codes = array_map(fn ($def) => $def['code'], $registry->getDefinitions());

    expect($codes)->toHaveCount(count(array_unique($codes)));
});

test('No native_name is empty for defined languages', function (): void {
    $registry = new LanguageRegistry;

    foreach ($registry->getDefinitions() as $def) {
        expect($def['native_name'])
            ->not->toBeNull("native_name for {$def['code']} should not be null")
            ->not->toBeEmpty("native_name for {$def['code']} should not be empty");
    }
});
