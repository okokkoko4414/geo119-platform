<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class OptimizationResultSeeder extends Seeder
{
    private const LOCALES = ['vi', 'th', 'id', 'ms', 'zh', 'ja', 'ko'];

    private const TYPES = ['grammar', 'clarity', 'tone', 'conciseness', 'fluency', 'full'];

    private const SAMPLE_TEXTS = [
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

    public function run(): void
    {
        $rows = [];
        $now = now();

        for ($daysAgo = 14; $daysAgo >= 0; $daysAgo--) {
            $resultsPerDay = random_int(3, 15);

            for ($i = 0; $i < $resultsPerDay; $i++) {
                $createdAt = $now->copy()
                    ->subDays($daysAgo)
                    ->setTime(random_int(0, 23), random_int(0, 59), random_int(0, 59));

                $sourceText = self::SAMPLE_TEXTS[array_rand(self::SAMPLE_TEXTS)];
                $locale = self::LOCALES[array_rand(self::LOCALES)];
                $type = self::TYPES[array_rand(self::TYPES)];

                // Simulate improvement: before 40-75%, after 65-95%
                $beforeScore = round(mt_rand(4000, 7500) / 10000, 4);
                $afterScore = round(min(0.95, $beforeScore + (mt_rand(500, 4000) / 10000)), 4);
                $improvement = $beforeScore > 0
                    ? round(($afterScore - $beforeScore) / $beforeScore, 4)
                    : 0.0;

                $inputTokens = random_int(20, 200);
                $outputTokens = random_int(20, 200);
                $latencyMs = random_int(200, 3000);
                $fromCache = random_int(1, 100) <= 20; // 20% cache hit rate
                $id = Uuid::uuid4()->toString();
                $sourceHash = hash('sha256', "{$sourceText}|{$locale}|{$type}");

                $rows[] = [
                    'id' => $id,
                    'source_text' => $sourceText,
                    'optimized_text' => $fromCache
                        ? $sourceText
                        : '[Optimized] '.$sourceText,
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
                    'source_hash' => $sourceHash,
                    'from_cache' => $fromCache,
                    'cached_at' => $createdAt->toIso8601String(),
                    'created_at' => $createdAt->toIso8601String(),
                    'updated_at' => $createdAt->toIso8601String(),
                ];

                if (count($rows) >= 50) {
                    DB::table('optimization_results')->insert($rows);
                    $rows = [];
                }
            }
        }

        if ($rows !== []) {
            DB::table('optimization_results')->insert($rows);
        }
    }
}
