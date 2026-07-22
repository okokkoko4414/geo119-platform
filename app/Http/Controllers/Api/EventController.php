<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EventTracking\EventTracker;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

            $lastId = '$';

            while (! connection_aborted()) {
                $entries = $this->tracker->readStream($lastId);

                if ($entries !== []) {
                    $counters = $this->tracker->todayCounters();

                    echo "event: counters\n";
                    echo 'data: '.json_encode($counters, JSON_THROW_ON_ERROR)."\n\n";

                    $lastEntry = end($entries);
                    if ($lastEntry !== false) {
                        $lastId = $lastEntry['id'];
                    }
                }

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

                sleep(1);
            }
        }, Response::HTTP_OK, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
