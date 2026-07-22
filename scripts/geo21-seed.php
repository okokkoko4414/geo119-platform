<?php

/**
 * GEOA-21: Seed events + optimization results for dashboard verification.
 * Run: php scripts/geo21-seed.php
 *
 * Produces enough data for the dashboard to show:
 *   - Impressions & clicks in overview cards
 *   - Time series chart (daily impressions/clicks)
 *   - Language breakdown table with CTR + % change
 *   - Recent optimizations with before/after scores
 */

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

$now = now();
$locales = ['en', 'vi', 'th', 'id', 'ms', 'zh', 'ja', 'ko', 'ar', 'ru', 'it', 'nl', 'de', 'fr', 'pt', 'es', 'hi', 'bn', 'pl', 'ta', 'tl'];
$countries = ['US', 'VN', 'TH', 'ID', 'MY', 'CN', 'JP', 'KR', 'SA', 'RU', 'IT', 'NL', 'DE', 'FR', 'BR', 'ES', 'IN', 'BD', 'PL', 'LK', 'PH'];
$devices = ['desktop', 'mobile', 'tablet'];
$browsers = ['Chrome', 'Safari', 'Firefox', 'Edge'];

// --- 1. Seed events (last 7 days) ---
echo "Seeding events...\n";

$eventsBatch = [];
$totalEvents = 0;

for ($daysAgo = 7; $daysAgo >= 0; $daysAgo--) {
    $date = $now->copy()->subDays($daysAgo);
    $impressionsPerDay = random_int(300, 800);

    for ($i = 0; $i < $impressionsPerDay; $i++) {
        $createdAt = $date->copy()->setTime(random_int(0, 23), random_int(0, 59), random_int(0, 59));
        $locale = $locales[array_rand($locales)];

        $eventsBatch[] = [
            'event_type' => 'impression',
            'user_id' => random_int(0, 1) ? Uuid::uuid4()->toString() : null,
            'session_id' => bin2hex(random_bytes(16)),
            'locale' => $locale,
            'country' => $countries[array_rand($countries)],
            'device_type' => $devices[array_rand($devices)],
            'browser' => $browsers[array_rand($browsers)],
            'is_bot' => false,
            'target_url' => 'https://geo119.com/'.$locale.'/',
            'referrer_url' => random_int(0, 1) ? 'https://google.com/' : null,
            'metadata' => json_encode(['page_type' => 'home']),
            'created_at' => $createdAt->toIso8601String(),
            'updated_at' => $createdAt->toIso8601String(),
        ];

        // ~12% of impressions get a click within 5-300s
        if (random_int(1, 100) <= 12) {
            $clickTime = $createdAt->copy()->addSeconds(random_int(5, 300));
            $eventsBatch[] = [
                'event_type' => 'click',
                'user_id' => $eventsBatch[count($eventsBatch) - 1]['user_id'],
                'session_id' => $eventsBatch[count($eventsBatch) - 1]['session_id'],
                'locale' => $locale,
                'country' => $eventsBatch[count($eventsBatch) - 1]['country'],
                'device_type' => $eventsBatch[count($eventsBatch) - 1]['device_type'],
                'browser' => $eventsBatch[count($eventsBatch) - 1]['browser'],
                'is_bot' => false,
                'target_url' => 'https://geo119.com/'.$locale.'/pricing',
                'referrer_url' => $eventsBatch[count($eventsBatch) - 1]['target_url'],
                'metadata' => json_encode(['page_type' => 'pricing']),
                'created_at' => $clickTime->toIso8601String(),
                'updated_at' => $clickTime->toIso8601String(),
            ];
        }

        if (count($eventsBatch) >= 500) {
            DB::table('events')->insert($eventsBatch);
            $totalEvents += count($eventsBatch);
            echo "  Inserted batch: {$totalEvents} events so far\n";
            $eventsBatch = [];
        }
    }
}

if ($eventsBatch !== []) {
    DB::table('events')->insert($eventsBatch);
    $totalEvents += count($eventsBatch);
}

echo "Events seeded: {$totalEvents}\n";

// Refresh materialized view
DB::statement('REFRESH MATERIALIZED VIEW event_aggregates_hourly');
echo "Materialized view refreshed.\n";

// --- 2. Seed optimization results (last 7 days) ---
echo "Seeding optimization results...\n";

$optTypes = ['grammar', 'clarity', 'tone', 'conciseness', 'fluency', 'full'];
$sampleTexts = [
    'The quick brown fox jumps over the lazy dog. This sentence contains every letter of the alphabet.',
    'Our platform provides best-in-class analytics for language market expansion.',
    'Click here to get started with your free trial today. No credit card required.',
    'The system automatically detects and corrects grammar errors in real-time.',
    'We help businesses reach new markets through AI-powered localization.',
    'Your payment has been processed successfully. A receipt has been sent to your email.',
    'Due to the complexity of the request, additional processing time may be required.',
    'Users can configure their preferences through the settings panel at any time.',
    'The application has encountered an unexpected error. Please try again later.',
    'All data is encrypted in transit and at rest using industry-standard protocols.',
];

$optBatch = [];
$totalOpts = 0;

for ($daysAgo = 7; $daysAgo >= 0; $daysAgo--) {
    $resultsPerDay = random_int(8, 20);

    for ($i = 0; $i < $resultsPerDay; $i++) {
        $createdAt = $now->copy()
            ->subDays($daysAgo)
            ->setTime(random_int(0, 23), random_int(0, 59), random_int(0, 59));

        $sourceText = $sampleTexts[array_rand($sampleTexts)];
        $locale = $locales[array_rand($locales)];
        $type = $optTypes[array_rand($optTypes)];

        // Simulate improvement: before 40-75%, after 65-95%
        $beforeScore = round(mt_rand(4000, 7500) / 10000, 4);
        $afterScore = round(min(0.95, $beforeScore + (mt_rand(500, 4000) / 10000)), 4);
        $improvement = $beforeScore > 0
            ? round(($afterScore - $beforeScore) / $beforeScore, 4)
            : 0.0;

        $inputTokens = random_int(20, 200);
        $outputTokens = random_int(20, 200);
        $latencyMs = random_int(200, 3000);
        $fromCache = random_int(1, 100) <= 20;

        $optBatch[] = [
            'id' => Uuid::uuid4()->toString(),
            'source_text' => $sourceText,
            'optimized_text' => $fromCache ? $sourceText : '[Optimized] '.$sourceText,
            'target_locale' => $locale,
            'optimization_type' => $type,
            'before_score' => $beforeScore,
            'after_score' => $afterScore,
            'improvement' => $improvement,
            'cost_cents' => round(($inputTokens * 0.014 + $outputTokens * 0.028) / 1000, 6),
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'model' => 'deepseek-chat',
            'latency_ms' => $latencyMs,
            'source_hash' => hash('sha256', "{$sourceText}|{$locale}|{$type}"),
            'from_cache' => $fromCache,
            'cached_at' => $createdAt->toIso8601String(),
            'created_at' => $createdAt->toIso8601String(),
            'updated_at' => $createdAt->toIso8601String(),
        ];

        if (count($optBatch) >= 50) {
            DB::table('optimization_results')->insert($optBatch);
            $totalOpts += count($optBatch);
            echo "  Inserted batch: {$totalOpts} optimization results so far\n";
            $optBatch = [];
        }
    }
}

if ($optBatch !== []) {
    DB::table('optimization_results')->insert($optBatch);
    $totalOpts += count($optBatch);
}

echo "Optimization results seeded: {$totalOpts}\n";
echo "Done.\n";
