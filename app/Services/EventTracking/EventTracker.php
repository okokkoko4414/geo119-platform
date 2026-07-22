<?php

declare(strict_types=1);

namespace App\Services\EventTracking;

use App\Models\Event;
use App\Services\Contracts\RedisStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

final class EventTracker
{
    private const STREAM_KEY = 'events:stream';
    private const MAX_STREAM_LENGTH = 100_000;

    public function __construct(
        private readonly UserAgentParser $uaParser,
        private readonly RedisStore $redis,
    ) {}

    /**
     * Track an event: enrich, persist to PostgreSQL, publish to Redis Stream.
     */
    public function track(array $payload, Request $request): void
    {
        $ua = $this->uaParser->parse($request->userAgent() ?? '');

        $event = Event::create([
            'event_type' => $payload['type'],
            'user_id' => $payload['user_id'] ?? null,
            'session_id' => $payload['session_id'] ?? $request->cookie('geo119_session'),
            'locale' => $payload['locale'] ?? app()->getLocale(),
            'country' => $payload['country'] ?? $this->resolveCountry($request),
            'device_type' => $ua['device_type'],
            'browser' => $ua['browser'],
            'is_bot' => $ua['is_bot'],
            'target_url' => $payload['target'] ?? null,
            'referrer_url' => $request->header('Referer'),
            'metadata' => $payload['metadata'] ?? null,
        ]);

        Redis::xadd(self::STREAM_KEY, '*', [
            'event_id' => (string) $event->id,
            'event_type' => $event->event_type,
            'locale' => $event->locale,
            'country' => $event->country ?? '',
            'device_type' => $event->device_type,
            'is_bot' => $event->is_bot ? '1' : '0',
            'timestamp' => (string) $event->created_at->timestamp,
        ]);

        Redis::xtrim(self::STREAM_KEY, 'MAXLEN', '~', (string) self::MAX_STREAM_LENGTH);

        if (! $ua['is_bot']) {
            $this->incrementCounters($event);
        }
    }

    /**
     * Read new events from the stream since the given ID.
     *
     * @return list<array{id: string, fields: array<string, string>}>
     */
    public function readStream(string $lastId = '0'): array
    {
        $result = Redis::xread([self::STREAM_KEY => $lastId], 1, 5000);

        if ($result === null || $result === false) {
            return [];
        }

        $entries = [];
        foreach ($result as $stream => $messages) {
            foreach ($messages as $id => $fields) {
                $entries[] = ['id' => $id, 'fields' => $fields];
            }
        }

        return $entries;
    }

    /**
     * @return array{impressions: int, clicks: int, ctr: float|null}
     */
    public function todayCounters(): array
    {
        $today = now()->toDateString();
        $impressions = (int) ($this->redis->get("counters:{$today}:impressions") ?? '0');
        $clicks = (int) ($this->redis->get("counters:{$today}:clicks") ?? '0');

        return [
            'impressions' => $impressions,
            'clicks' => $clicks,
            'ctr' => $impressions > 0 ? round($clicks / $impressions * 100, 2) : null,
        ];
    }

    private function incrementCounters(Event $event): void
    {
        $today = now()->toDateString();

        if ($event->event_type === 'impression') {
            $this->redis->incr("counters:{$today}:impressions");
        } elseif ($event->event_type === 'click') {
            $this->redis->incr("counters:{$today}:clicks");
        }
    }

    private function resolveCountry(Request $request): ?string
    {
        $cfCountry = $request->header('CF-IPCountry');

        if (is_string($cfCountry) && $cfCountry !== '' && $cfCountry !== 'XX') {
            return strtoupper($cfCountry);
        }

        return null;
    }
}
