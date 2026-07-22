<?php

declare(strict_types=1);

use App\Models\Language;
use App\Services\LanguageRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    Config::set('languages.rtl', ['ar']);
    Config::set('languages.gendered', ['fr', 'ar']);
});

it('loads language definitions from config', function (): void {
    $registry = new LanguageRegistry;

    expect($registry->definitionCount())->toBe(4);
});

it('finds a definition by code', function (): void {
    $registry = new LanguageRegistry;

    $def = $registry->getDefinition('vi');

    expect($def)->not->toBeNull()
        ->and($def['name'])->toBe('Vietnamese')
        ->and($def['tier'])->toBe(1);
});

it('returns null for unknown language code', function (): void {
    $registry = new LanguageRegistry;

    expect($registry->getDefinition('xx'))->toBeNull();
});

it('activates a language from config definition', function (): void {
    $registry = new LanguageRegistry;
    $lang = $registry->activate('vi');

    expect($lang)->toBeInstanceOf(Language::class)
        ->and($lang->code)->toBe('vi')
        ->and($lang->is_active)->toBeTrue()
        ->and($lang->tier)->toBe(1);
});

it('throws exception when activating unknown language', function (): void {
    $registry = new LanguageRegistry;

    $registry->activate('xx');
})->throws(RuntimeException::class);

it('deactivates a language', function (): void {
    $registry = new LanguageRegistry;
    $registry->activate('vi');
    $registry->deactivate('vi');

    $lang = $registry->findByCode('vi');
    expect($lang->is_active)->toBeFalse();
});

it('boots all languages from config', function (): void {
    $registry = new LanguageRegistry;
    $registry->boot();

    expect(Language::active()->count())->toBe(4);
});

it('identifies RTL languages', function (): void {
    $registry = new LanguageRegistry;

    expect($registry->isRtl('ar'))->toBeTrue()
        ->and($registry->isRtl('en'))->toBeFalse();
});

it('identifies gendered languages', function (): void {
    $registry = new LanguageRegistry;

    expect($registry->isGendered('fr'))->toBeTrue()
        ->and($registry->isGendered('en'))->toBeFalse();
});

it('returns quality threshold per tier', function (): void {
    $registry = new LanguageRegistry;

    expect($registry->getQualityThreshold(1))->toBe(0.85)
        ->and($registry->getQualityThreshold(2))->toBe(0.68)
        ->and($registry->getQualityThreshold(3))->toBe(0.70);
});

it('returns baseline language codes', function (): void {
    $registry = new LanguageRegistry;

    expect($registry->getBaselineLanguages())->toBe(['en', 'vi']);
});

it('sets and updates quality scores', function (): void {
    $registry = new LanguageRegistry;
    $registry->activate('en');
    $registry->setQualityScore('en', 0.92);

    $lang = $registry->findByCode('en');
    expect($lang->quality_score)->toBe(0.92);
});
