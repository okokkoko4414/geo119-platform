<?php

declare(strict_types=1);

use App\Models\Language;
use App\Models\Translation;
use App\Services\ClaudeLocal\ClaudeLocalClient;
use App\Services\LanguageRegistry;
use App\Services\QualityGate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('languages.languages', [
        ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'tier' => 1],
        ['code' => 'vi', 'name' => 'Vietnamese', 'native_name' => 'Tiếng Việt', 'tier' => 1],
        ['code' => 'th', 'name' => 'Thai', 'native_name' => 'ไทย', 'tier' => 2],
    ]);
    Config::set('languages.tiers', [
        1 => ['threshold' => 0.85, 'label' => 'Premium'],
        2 => ['threshold' => 0.68, 'label' => 'Beta'],
        3 => ['threshold' => 0.70, 'label' => 'Community'],
    ]);
    Config::set('languages.baseline_languages', ['en', 'vi']);
});

it('returns 0.0 for empty translation or source', function (): void {
    $gate = app(QualityGate::class);

    expect($gate->score('', 'hello', 'vi'))->toBe(0.0)
        ->and($gate->score('xin chao', '', 'vi'))->toBe(0.0);
});

it('scores a translation via AI', function (): void {
    $ai = mock(ClaudeLocalClient::class);
    $ai->shouldReceive('chat')
        ->once()
        ->andReturn(['content' => '0.92']);

    $registry = new LanguageRegistry();
    $gate = new QualityGate($registry, $ai);

    $score = $gate->score('xin chao', 'hello', 'vi');
    expect($score)->toBe(0.92);
});

it('returns neutral score when AI fails', function (): void {
    $ai = mock(ClaudeLocalClient::class);
    $ai->shouldReceive('chat')
        ->once()
        ->andThrow(new \RuntimeException('timeout'));

    $registry = new LanguageRegistry();
    $gate = new QualityGate($registry, $ai);

    $score = $gate->score('xin chao', 'hello', 'vi');
    expect($score)->toBe(0.5);
});

it('clamps score to 0.0-1.0 range', function (): void {
    $ai = mock(ClaudeLocalClient::class);
    $ai->shouldReceive('chat')->andReturn(['content' => '1.5']);
    $registry = new LanguageRegistry();
    $gate = new QualityGate($registry, $ai);

    expect($gate->score('a', 'b', 'vi'))->toBe(1.0);
});

it('detects hallucinations below threshold', function (): void {
    $gate = app(QualityGate::class);

    expect($gate->isHallucination(0.2))->toBeTrue()
        ->and($gate->isHallucination(0.5))->toBeFalse()
        ->and($gate->needsHumanReview(0.2))->toBeTrue()
        ->and($gate->needsHumanReview(0.5))->toBeFalse();
});

it('determines threshold per tier', function (): void {
    $registry = new LanguageRegistry();
    $gate = new QualityGate($registry, mock(ClaudeLocalClient::class));

    $tier1Lang = new Language(['tier' => 1]);
    $tier2Lang = new Language(['tier' => 2, 'baseline_score' => 0.90]);
    $tier3Lang = new Language(['tier' => 3]);

    expect($gate->thresholdForLanguage($tier1Lang))->toBe(0.85)
        ->and($gate->thresholdForLanguage($tier2Lang))->toBe(0.72)  // 0.90 * 0.8
        ->and($gate->thresholdForLanguage($tier3Lang))->toBe(0.70);
});

it('evaluates a language with translations', function (): void {
    $lang = Language::create([
        'code' => 'vi', 'name' => 'Vietnamese', 'tier' => 1, 'is_active' => true,
    ]);

    Translation::create([
        'locale' => 'vi', 'namespace' => 'ui', 'key' => 'hello',
        'value' => 'xin chao', 'source_value' => 'hello', 'quality_score' => 0.90,
    ]);
    Translation::create([
        'locale' => 'vi', 'namespace' => 'ui', 'key' => 'goodbye',
        'value' => 'tam biet', 'source_value' => 'goodbye', 'quality_score' => 0.88,
    ]);

    $gate = app(QualityGate::class);
    $report = $gate->evaluateLanguage($lang);

    expect($report['passes'])->toBeTrue()
        ->and($report['average_score'])->toBe(0.89)
        ->and($report['total_translations'])->toBe(2);
});

it('evaluates empty language as failing', function (): void {
    $lang = Language::create([
        'code' => 'vi', 'name' => 'Vietnamese', 'tier' => 1, 'is_active' => true,
    ]);

    $gate = app(QualityGate::class);
    $report = $gate->evaluateLanguage($lang);

    expect($report['passes'])->toBeFalse()
        ->and($report['total_translations'])->toBe(0);
});

it('detects regression exceeding 2% delta', function (): void {
    Language::create([
        'code' => 'en', 'name' => 'English', 'tier' => 1, 'is_active' => true,
        'baseline_score' => 0.91,
    ]);

    Translation::create([
        'locale' => 'en', 'namespace' => 'ui', 'key' => 'test',
        'value' => 'test', 'source_value' => 'test', 'quality_score' => 0.88,
    ]);

    $gate = app(QualityGate::class);
    $regressions = $gate->regressionTest();

    // 0.88 is 0.03 below baseline 0.91 (>2%), so regression detected
    expect($regressions)->not->toBeEmpty();
});

it('passes regression test when delta is within 2%', function (): void {
    Language::create([
        'code' => 'en', 'name' => 'English', 'tier' => 1, 'is_active' => true,
        'baseline_score' => 0.90,
    ]);

    Translation::create([
        'locale' => 'en', 'namespace' => 'ui', 'key' => 'test',
        'value' => 'test', 'source_value' => 'test', 'quality_score' => 0.89,
    ]);

    $gate = app(QualityGate::class);
    $regressions = $gate->regressionTest();

    expect($regressions)->toBeEmpty();
});

it('computes coverage ratio', function (): void {
    Translation::create([
        'locale' => 'en', 'namespace' => 'ui', 'key' => 'key1',
        'value' => 'value1', 'source_value' => 'value1', 'quality_score' => 1.0,
    ]);
    Translation::create([
        'locale' => 'en', 'namespace' => 'ui', 'key' => 'key2',
        'value' => 'value2', 'source_value' => 'value2', 'quality_score' => 1.0,
    ]);
    Translation::create([
        'locale' => 'vi', 'namespace' => 'ui', 'key' => 'key1',
        'value' => 'xin chao', 'source_value' => 'value1', 'quality_score' => 0.9,
    ]);

    $gate = app(QualityGate::class);
    $coverage = $gate->coverage('vi');

    expect($coverage)->toBe(0.5);
});

it('generates full quality report', function (): void {
    Language::create([
        'code' => 'en', 'name' => 'English', 'tier' => 1, 'is_active' => true,
        'baseline_score' => 0.95,
    ]);
    Translation::create([
        'locale' => 'en', 'namespace' => 'ui', 'key' => 'test',
        'value' => 'test', 'source_value' => 'test', 'quality_score' => 0.95,
    ]);

    $gate = app(QualityGate::class);
    $report = $gate->fullReport();

    expect($report)->toHaveKeys(['generated_at', 'total_languages', 'languages', 'tier1_average', 'regressions'])
        ->and($report['total_languages'])->toBe(1);
});
