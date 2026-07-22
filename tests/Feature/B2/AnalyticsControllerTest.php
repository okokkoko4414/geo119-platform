<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Seed events spanning 3 days for time-series and language breakdown coverage
    $locales = ['en', 'vi', 'th'];
    $now = now();

    for ($daysAgo = 2; $daysAgo >= 0; $daysAgo--) {
        $date = $now->copy()->subDays($daysAgo);

        foreach ($locales as $locale) {
            $impressions = $daysAgo === 0 ? 100 : 200;
            $clicks = $daysAgo === 0 ? 20 : 30;

            for ($i = 0; $i < $impressions; $i++) {
                Event::create([
                    'event_type' => 'impression',
                    'locale' => $locale,
                    'device_type' => 'desktop',
                    'browser' => 'Chrome',
                    'is_bot' => false,
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);
            }

            for ($i = 0; $i < $clicks; $i++) {
                Event::create([
                    'event_type' => 'click',
                    'locale' => $locale,
                    'device_type' => 'desktop',
                    'browser' => 'Chrome',
                    'is_bot' => false,
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);
            }
        }
    }

    // Refresh the materialized view so queries see the data
    DB::statement('REFRESH MATERIALIZED VIEW event_aggregates_hourly');
});

it('returns time series data', function (): void {
    $response = $this->getJson('/api/v1/analytics/time-series?days=30');

    $response->assertOk();
    $data = $response->json();

    expect($data)->toBeArray()
        ->and($data)->not->toBeEmpty();

    $first = $data[0];
    expect($first)->toHaveKeys(['day', 'impressions', 'clicks']);
});

it('respects days query parameter', function (): void {
    $response = $this->getJson('/api/v1/analytics/time-series?days=1');

    $response->assertOk();
    $data = $response->json();

    expect(count($data))->toBeLessThanOrEqual(2);
});

it('returns language breakdown sorted by CTR', function (): void {
    $response = $this->getJson('/api/v1/analytics/language-breakdown');

    $response->assertOk();
    $data = $response->json();

    expect($data)->toBeArray()
        ->and($data)->not->toBeEmpty();

    $first = $data[0];
    expect($first)->toHaveKeys(['locale', 'impressions', 'clicks', 'ctr', 'pct_change']);

    // Verify sorted by CTR descending
    $ctrs = array_column($data, 'ctr');
    $sorted = $ctrs;
    rsort($sorted, SORT_NUMERIC);
    expect($ctrs)->toBe($sorted);
});

it('dashboard page loads successfully', function (): void {
    $this->withoutVite();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/en/dashboard/analytics');

    $response->assertOk()
        ->assertViewIs('pages.analytics.dashboard')
        ->assertViewHas(['impressions', 'clicks', 'ctr']);
});
