<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EventTracking\EventTracker;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EventController extends Controller
{
    public function __construct(
        private readonly EventTracker $tracker,
    ) {}

    /**
     * POST /api/e/track
     *
     * Accepts {type, target, metadata, user_id, session_id, locale, country}.
     * Enriches with geo-IP, user-agent parse, session_id. Returns 204.
     */
    public function track(Request $request): Response
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:impression,click', 'max:50'],
            'target' => ['nullable', 'string', 'max:2048'],
            'metadata' => ['nullable', 'array'],
            'user_id' => ['nullable', 'uuid'],
            'session_id' => ['nullable', 'string', 'max:64'],
            'locale' => ['nullable', 'string', 'max:10'],
            'country' => ['nullable', 'string', 'max:2'],
        ]);

        $this->tracker->track($validated, $request);

        return response()->noContent();
    }

    /**
     * GET /api/e/live
     *
     * Server-Sent Events stream of live event counters.
     * Pushes aggregated counters every second.
     */
    public function live(): StreamedResponse
    {
        return response()->stream(function (): void {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('X-Accel-Buffering: no');

            $lastEventId = '0';
            $lastOptId = '0';
            $optStreamInitialized = false;

            while (! connection_aborted()) {
                $hasEvents = false;

                // Event tracking stream (impressions/clicks)
                $entries = $this->tracker->readStream($lastEventId);
                if ($entries !== []) {
                    $counters = $this->tracker->todayCounters();

                    echo "event: counters\n";
                    echo 'data: '.json_encode($counters, JSON_THROW_ON_ERROR)."\n\n";

                    $lastEntry = end($entries);
                    if ($lastEntry !== false) {
                        $lastEventId = $lastEntry['id'];
                    }
                    $hasEvents = true;
                }

                // Optimization stream (B3 results)
                // Skip historical entries on first connect — only deliver new events
                if (! $optStreamInitialized) {
                    $maxEntry = Redis::xrevrange('optimizations:stream', '+', '-', 1);
                    $lastOptId = $maxEntry !== false && $maxEntry !== [] ? (string) array_key_first($maxEntry) : '0';
                    $optStreamInitialized = true;
                } else {
                    $optEntries = Redis::xread(['optimizations:stream' => $lastOptId], 1, 100);
                    if ($optEntries !== null && $optEntries !== false) {
                        foreach ($optEntries as $stream => $messages) {
                            foreach ($messages as $id => $fields) {
                                echo "event: optimization\n";
                                echo 'data: '.json_encode([
                                    'id' => $fields['id'] ?? '',
                                    'target_locale' => $fields['target_locale'] ?? '',
                                    'optimization_type' => $fields['optimization_type'] ?? '',
                                    'before_score' => (float) ($fields['before_score'] ?? 0),
                                    'after_score' => (float) ($fields['after_score'] ?? 0),
                                    'improvement' => (float) ($fields['improvement'] ?? 0),
                                ], JSON_THROW_ON_ERROR)."\n\n";
                                $lastOptId = $id;
                                $hasEvents = true;
                            }
                        }
                    }
                }

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

                // Push at least every 1s; when idle, push counters anyway so the client stays fresh
                if (! $hasEvents) {
                    $counters = $this->tracker->todayCounters();
                    echo "event: counters\n";
                    echo 'data: '.json_encode($counters, JSON_THROW_ON_ERROR)."\n\n";
                    flush();
                }

                sleep(1);
            }
        }, Response::HTTP_OK, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
