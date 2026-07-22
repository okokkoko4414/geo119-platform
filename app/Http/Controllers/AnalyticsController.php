<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\OptimizationResult;
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

        $optimizations = OptimizationResult::query()
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (OptimizationResult $r): array => [
                'id' => $r->id,
                'target_locale' => $r->target_locale,
                'optimization_type' => $r->optimization_type,
                'before_score' => $r->before_score,
                'after_score' => $r->after_score,
                'improvement_pct' => round($r->improvement * 100, 1),
                'cost_cents' => $r->cost_cents,
                'from_cache' => $r->from_cache,
                'created_at' => $r->created_at,
            ]);

        return view('pages.analytics.dashboard', [
            'locale' => app()->getLocale(),
            'impressions' => $counters['impressions'],
            'clicks' => $counters['clicks'],
            'ctr' => $counters['ctr'],
            'optimizations' => $optimizations,
        ]);
    }

    /**
     * GET /api/analytics/impressions
     *
     * Returns per-language impression totals, optionally filtered by ?locale=X.
     */
    public function impressions(Request $request): JsonResponse
    {
        $query = DB::table('event_aggregates_hourly')
            ->selectRaw('locale, SUM(event_count) AS total')
            ->where('event_type', 'impression')
            ->groupBy('locale')
            ->orderByDesc('total');

        if ($locale = $request->query('locale')) {
            $query->where('locale', $locale);
        }

        return response()->json($query->get());
    }

    /**
     * GET /api/analytics/clicks
     *
     * Returns per-language click totals, optionally filtered by ?locale=X.
     */
    public function clicks(Request $request): JsonResponse
    {
        $query = DB::table('event_aggregates_hourly')
            ->selectRaw('locale, SUM(event_count) AS total')
            ->where('event_type', 'click')
            ->groupBy('locale')
            ->orderByDesc('total');

        if ($locale = $request->query('locale')) {
            $query->where('locale', $locale);
        }

        return response()->json($query->get());
    }

    /**
     * GET /api/analytics/ctr
     *
     * Returns per-language CTR, optionally filtered by ?locale=X.
     */
    public function ctr(Request $request): JsonResponse
    {
        $query = DB::table('event_aggregates_hourly')
            ->selectRaw("
                locale,
                SUM(CASE WHEN event_type = 'impression' THEN event_count ELSE 0 END) AS impressions,
                SUM(CASE WHEN event_type = 'click' THEN event_count ELSE 0 END) AS clicks
            ")
            ->groupBy('locale');

        if ($locale = $request->query('locale')) {
            $query->where('locale', $locale);
        }

        $results = $query->get()->map(function ($row) {
            $impressions = (int) $row->impressions;

            return [
                'locale' => $row->locale,
                'impressions' => $impressions,
                'clicks' => (int) $row->clicks,
                'ctr' => $impressions > 0 ? round((int) $row->clicks / $impressions * 100, 2) : null,
            ];
        });

        return response()->json($results);
    }

    /**
     * GET /api/analytics/time-series
     *
     * Returns daily impressions + clicks for the last N days.
     * Optional ?locale=X filter.
     */
    public function timeSeries(Request $request): JsonResponse
    {
        $days = min((int) $request->query('days', '30'), 90);

        $query = DB::table('event_aggregates_hourly')
            ->selectRaw("
                date_trunc('day', hour) AS day,
                event_type,
                SUM(event_count) AS total
            ")
            ->where('hour', '>=', now()->subDays($days)->startOfDay())
            ->whereIn('event_type', ['impression', 'click']);

        if ($locale = $request->query('locale')) {
            $query->where('locale', $locale);
        }

        $rows = $query
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
     * Optional ?locale=X filter.
     */
    public function languageBreakdown(Request $request): JsonResponse
    {
        $localeFilter = $request->query('locale');

        $today = DB::table('event_aggregates_hourly')
            ->selectRaw("
                locale,
                SUM(CASE WHEN event_type = 'impression' THEN event_count ELSE 0 END) AS impressions,
                SUM(CASE WHEN event_type = 'click' THEN event_count ELSE 0 END) AS clicks
            ")
            ->where('hour', '>=', now()->startOfDay())
            ->when($localeFilter, fn ($q) => $q->where('locale', $localeFilter))
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
            ->when($localeFilter, fn ($q) => $q->where('locale', $localeFilter))
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

    /**
     * GET /api/analytics/dashboard
     *
     * Returns real-time summary with impression count, date range,
     * and per-language breakdown for 8 core languages.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->subDays(30)->toDateString());
        $to = $request->query('to', now()->toDateString());

        $coreLanguages = ['en', 'zh', 'ja', 'ko', 'fr', 'de', 'es', 'pt'];

        $count = (int) DB::table('event_aggregates_hourly')
            ->where('event_type', 'impression')
            ->whereBetween(DB::raw('hour::date'), [$from, $to])
            ->sum('event_count');

        $rows = DB::table('event_aggregates_hourly')
            ->selectRaw("locale, event_type, SUM(event_count) AS total")
            ->whereIn('locale', $coreLanguages)
            ->whereIn('event_type', ['impression', 'click'])
            ->whereBetween(DB::raw('hour::date'), [$from, $to])
            ->groupBy('locale', 'event_type')
            ->get()
            ->groupBy('locale');

        $languages = collect($coreLanguages)->map(function (string $lang) use ($rows): array {
            $byType = $rows->get($lang, collect())->groupBy('event_type');

            $impressions = (int) ($byType->get('impression')?->sum('total') ?? 0);
            $clicks = (int) ($byType->get('click')?->sum('total') ?? 0);
            $ctr = $impressions > 0 ? round($clicks / $impressions * 100, 2) : 0.0;

            return [
                'language' => $lang,
                'impressions' => $impressions,
                'clicks' => $clicks,
                'ctr' => $ctr,
            ];
        });

        return response()->json([
            'impressions_count' => $count,
            'date_from' => $from,
            'date_to' => $to,
            'languages' => $languages,
        ]);
    }
}
