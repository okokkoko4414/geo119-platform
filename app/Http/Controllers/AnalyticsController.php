<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\EventTracking\EventTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class AnalyticsController extends Controller
{
    public function __construct(
        private readonly EventTracker $tracker,
    ) {}

    /**
     * GET /dashboard/analytics
     */
    public function index(): View
    {
        $counters = $this->tracker->todayCounters();

        return view('pages.analytics.dashboard', [
            'locale' => app()->getLocale(),
            'impressions' => $counters['impressions'],
            'clicks' => $counters['clicks'],
            'ctr' => $counters['ctr'],
        ]);
    }

    /**
     * GET /api/analytics/time-series
     *
     * Returns daily impressions + clicks for the last N days.
     */
    public function timeSeries(Request $request): JsonResponse
    {
        $days = min((int) $request->query('days', '30'), 90);

        $rows = DB::table('event_aggregates_hourly')
            ->selectRaw("
                date_trunc('day', hour) AS day,
                event_type,
                SUM(event_count) AS total
            ")
            ->where('hour', '>=', now()->subDays($days)->startOfDay())
            ->whereIn('event_type', ['impression', 'click'])
            ->groupBy(DB::raw("date_trunc('day', hour)"), 'event_type')
            ->orderBy('day')
            ->get();

        $series = [];
        foreach ($rows as $row) {
            $date = $row->day;
            $series[$date] ??= ['day' => $date, 'impressions' => 0, 'clicks' => 0];
            $series[$date][$row->event_type.'s'] = (int) $row->total;
        }

        return response()->json(array_values($series));
    }

    /**
     * GET /api/analytics/language-breakdown
     *
     * Returns per-language impressions, clicks, CTR, and % change vs previous period.
     */
    public function languageBreakdown(): JsonResponse
    {
        $today = DB::table('event_aggregates_hourly')
            ->selectRaw("
                locale,
                SUM(CASE WHEN event_type = 'impression' THEN event_count ELSE 0 END) AS impressions,
                SUM(CASE WHEN event_type = 'click' THEN event_count ELSE 0 END) AS clicks
            ")
            ->where('hour', '>=', now()->startOfDay())
            ->groupBy('locale')
            ->get()
            ->keyBy('locale');

        $yesterday = DB::table('event_aggregates_hourly')
            ->selectRaw("
                locale,
                SUM(CASE WHEN event_type = 'impression' THEN event_count ELSE 0 END) AS impressions,
                SUM(CASE WHEN event_type = 'click' THEN event_count ELSE 0 END) AS clicks
            ")
            ->whereBetween('hour', [now()->subDay()->startOfDay(), now()->startOfDay()])
            ->groupBy('locale')
            ->get()
            ->keyBy('locale');

        $breakdown = [];
        foreach ($today as $locale => $row) {
            $impressions = (int) $row->impressions;
            $clicks = (int) $row->clicks;
            $ctr = $impressions > 0 ? round($clicks / $impressions * 100, 2) : null;

            $yesterdayData = $yesterday->get($locale);
            $yesterdayCtr = null;
            if ($yesterdayData && (int) $yesterdayData->impressions > 0) {
                $yesterdayCtr = round((int) $yesterdayData->clicks / (int) $yesterdayData->impressions * 100, 2);
            }

            $pctChange = null;
            if ($ctr !== null && $yesterdayCtr !== null && $yesterdayCtr > 0) {
                $pctChange = round(($ctr - $yesterdayCtr) / $yesterdayCtr * 100, 1);
            }

            $breakdown[] = [
                'locale' => $locale,
                'impressions' => $impressions,
                'clicks' => $clicks,
                'ctr' => $ctr,
                'pct_change' => $pctChange,
            ];
        }

        usort($breakdown, fn (array $a, array $b): int => ($b['ctr'] ?? -1) <=> ($a['ctr'] ?? -1));

        return response()->json($breakdown);
    }
}
