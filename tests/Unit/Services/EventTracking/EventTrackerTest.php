<?php

declare(strict_types=1);

use App\Services\EventTracking\EventTracker;
use App\Services\EventTracking\UserAgentParser;
use Illuminate\Http\Request;
use Tests\Unit\Services\Optimization\FakeRedisStore;

beforeEach(function (): void {
    $this->redis = new FakeRedisStore;
    $this->uaParser = new UserAgentParser;
    $this->tracker = new EventTracker($this->uaParser, $this->redis);
});

it('returns CTR as null when impressions are zero', function (): void {
    $counters = $this->tracker->todayCounters();

    expect($counters['impressions'])->toBe(0)
        ->and($counters['clicks'])->toBe(0)
        ->and($counters['ctr'])->toBeNull();
});

it('uses date-stamped counter keys for todayCounters', function (): void {
    $today = now()->toDateString();
    $this->redis->set("counters:{$today}:impressions", '42');
    $this->redis->set("counters:{$today}:clicks", '7');

    $counters = $this->tracker->todayCounters();

    expect($counters['impressions'])->toBe(42)
        ->and($counters['clicks'])->toBe(7)
        ->and($counters['ctr'])->toBe(round(7 / 42 * 100, 2));
});

it('ignores stale counters from previous days', function (): void {
    $yesterday = now()->subDay()->toDateString();
    $today = now()->toDateString();

    $this->redis->set("counters:{$yesterday}:impressions", '999');
    $this->redis->set("counters:{$today}:impressions", '10');

    $counters = $this->tracker->todayCounters();

    expect($counters['impressions'])->toBe(10);
});

it('resolves country from Cloudflare IP header', function (): void {
    $request = Request::create('/', 'POST', [], [], [], [
        'HTTP_CF_IPCOUNTRY' => 'VN',
    ]);

    $ref = new ReflectionMethod(EventTracker::class, 'resolveCountry');
    $ref->setAccessible(true);

    expect($ref->invoke($this->tracker, $request))->toBe('VN');
});

it('ignores XX Cloudflare country code', function (): void {
    $request = Request::create('/', 'POST', [], [], [], [
        'HTTP_CF_IPCOUNTRY' => 'XX',
    ]);

    $ref = new ReflectionMethod(EventTracker::class, 'resolveCountry');
    $ref->setAccessible(true);

    expect($ref->invoke($this->tracker, $request))->toBeNull();
});

it('returns null when no Cloudflare header present', function (): void {
    $request = Request::create('/');

    $ref = new ReflectionMethod(EventTracker::class, 'resolveCountry');
    $ref->setAccessible(true);

    expect($ref->invoke($this->tracker, $request))->toBeNull();
});
