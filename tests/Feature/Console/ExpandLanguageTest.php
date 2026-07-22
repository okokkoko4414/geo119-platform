<?php

declare(strict_types=1);

use App\Jobs\TranslateStringJob;
use App\Models\Language;
use App\Models\Translation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('languages.languages', [
        ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'tier' => 1],
        ['code' => 'vi', 'name' => 'Vietnamese', 'native_name' => 'Tiếng Việt', 'tier' => 1],
    ]);
    Config::set('languages.baseline_languages', ['en', 'vi']);
    Config::set('languages.tiers', [
        1 => ['threshold' => 0.85, 'label' => 'Premium'],
        2 => ['threshold' => 0.68, 'label' => 'Beta'],
        3 => ['threshold' => 0.70, 'label' => 'Community'],
    ]);
});

it('rejects unknown language code', function (): void {
    $this->artisan('lang:expand', ['code' => 'xx'])
        ->assertExitCode(1);
});

it('shows info when language is already active', function (): void {
    Language::create([
        'code' => 'vi', 'name' => 'Vietnamese', 'native_name' => 'Tiếng Việt',
        'tier' => 1, 'is_active' => true,
    ]);

    Translation::create([
        'locale' => 'en', 'namespace' => 'ui', 'key' => 'hello',
        'value' => 'Hello', 'source_value' => 'Hello', 'quality_score' => 1.0,
    ]);

    $this->artisan('lang:expand', ['code' => 'vi'])
        ->assertExitCode(0);
});

it('shows dry run output without dispatching jobs', function (): void {
    Translation::create([
        'locale' => 'en', 'namespace' => 'ui', 'key' => 'hello',
        'value' => 'Hello', 'source_value' => 'Hello', 'quality_score' => 1.0,
    ]);
    Translation::create([
        'locale' => 'en', 'namespace' => 'ui', 'key' => 'goodbye',
        'value' => 'Goodbye', 'source_value' => 'Goodbye', 'quality_score' => 1.0,
    ]);

    Bus::fake();

    $this->artisan('lang:expand', ['code' => 'vi', '--dry-run' => true])
        ->assertExitCode(0);

    Bus::assertNotDispatched(TranslateStringJob::class);
});

it('dispatches jobs for new language', function (): void {
    Translation::create([
        'locale' => 'en', 'namespace' => 'ui', 'key' => 'hello',
        'value' => 'Hello', 'source_value' => 'Hello', 'quality_score' => 1.0,
    ]);

    Bus::fake();

    $this->artisan('lang:expand', ['code' => 'vi'])
        ->assertExitCode(0);

    Bus::assertDispatched(TranslateStringJob::class, 1);
});

it('fails when no source translations exist', function (): void {
    $this->artisan('lang:expand', ['code' => 'vi'])
        ->assertExitCode(1);
});

it('retranslates an existing language with force flag', function (): void {
    Language::create([
        'code' => 'vi', 'name' => 'Vietnamese', 'native_name' => 'Tiếng Việt',
        'tier' => 1, 'is_active' => true,
    ]);

    Translation::create([
        'locale' => 'en', 'namespace' => 'ui', 'key' => 'hello',
        'value' => 'Hello', 'source_value' => 'Hello', 'quality_score' => 1.0,
    ]);

    Bus::fake();

    $this->artisan('lang:expand', ['code' => 'vi', '--retranslate' => true])
        ->assertExitCode(0);

    Bus::assertDispatched(TranslateStringJob::class, 1);
});
