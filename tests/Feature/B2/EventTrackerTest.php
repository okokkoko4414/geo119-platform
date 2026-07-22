<?php

declare(strict_types=1);

use App\Services\EventTracking\EventTracker;
use App\Services\EventTracking\UserAgentParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Unit\Services\Optimization\FakeRedisStore;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->redis = new FakeRedisStore;
    $this->uaParser = new UserAgentParser;
    $this->tracker = new EventTracker($this->uaParser, $this->redis);
});

it('increments impression counter for impression events', function (): void {
    $request = Request::create('/', 'POST', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120',
        'HTTP_REFERER' => 'https://google.com/',
    ]);

    $this->tracker->track([
        'type' => 'impression',
        'target' => 'https://geo119.com/en/',
        'locale' => 'en',
    ], $request);

    $counters = $this->tracker->todayCounters();
    expect($counters['impressions'])->toBe(1);
});

it('increments click counter for click events', function (): void {
    $request = Request::create('/', 'POST', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120',
    ]);

    $this->tracker->track([
        'type' => 'click',
        'target' => 'https://geo119.com/en/pricing',
        'locale' => 'en',
    ], $request);

    $counters = $this->tracker->todayCounters();
    expect($counters['clicks'])->toBe(1);
});

it('does not increment counters for bot traffic', function (): void {
    $request = Request::create('/', 'POST', [], [], [], [
        'HTTP_USER_AGENT' => 'Googlebot/2.1',
    ]);

    $this->tracker->track([
        'type' => 'impression',
        'target' => 'https://geo119.com/en/',
        'locale' => 'en',
    ], $request);

    $counters = $this->tracker->todayCounters();
    expect($counters['impressions'])->toBe(0);
});

it('computes CTR correctly', function (): void {
    $request = Request::create('/', 'POST', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120',
    ]);

    $this->tracker->track(['type' => 'impression', 'target' => 'https://geo119.com/en/', 'locale' => 'en'], $request);
    $this->tracker->track(['type' => 'impression', 'target' => 'https://geo119.com/en/', 'locale' => 'en'], $request);
    $this->tracker->track(['type' => 'click', 'target' => 'https://geo119.com/en/', 'locale' => 'en'], $request);

    $counters = $this->tracker->todayCounters();
    expect($counters['impressions'])->toBe(2)
        ->and($counters['clicks'])->toBe(1)
        ->and($counters['ctr'])->toBe(50.0);
});

it('uses date-stamped counter keys for daily reset', function (): void {
    $request = Request::create('/', 'POST', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120',
    ]);

    $this->tracker->track(['type' => 'impression', 'target' => 'https://geo119.com/en/', 'locale' => 'en'], $request);

    $today = now()->toDateString();
    expect($this->redis->get("counters:{$today}:impressions"))->toBe('1');
});

it('persists event to database', function (): void {
    $request = Request::create('/', 'POST', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone) Chrome/120 Mobile',
        'HTTP_REFERER' => 'https://google.com/',
    ]);

    $this->tracker->track([
        'type' => 'impression',
        'target' => 'https://geo119.com/en/',
        'locale' => 'en',
        'user_id' => '550e8400-e29b-41d4-a716-446655440000',
    ], $request);

    $this->assertDatabaseHas('events', [
        'event_type' => 'impression',
        'locale' => 'en',
        'device_type' => 'mobile',
        'is_bot' => false,
        'target_url' => 'https://geo119.com/en/',
        'referrer_url' => 'https://google.com/',
    ]);
});

it('sets target_url to null when target not in payload', function (): void {
    $request = Request::create('/', 'POST', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120',
        'HTTP_REFERER' => 'https://referrer.com/',
    ]);

    $this->tracker->track(['type' => 'click', 'locale' => 'en'], $request);

    $this->assertDatabaseHas('events', [
        'target_url' => null,
        'referrer_url' => 'https://referrer.com/',
    ]);
});
