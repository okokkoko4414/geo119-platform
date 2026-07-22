<?php

declare(strict_types=1);

namespace App\Services\Optimization;

final readonly class BatchResultAggregator
{
    /** @param OptimizationResult[] $results */
    public function aggregate(array $results): array
    {
        $successes = [];
        $failures = [];
        $totalCostCents = 0.0;
        $totalWords = 0;
        $totalLatencyMs = 0;
        $cacheHits = 0;
        $totalInputTokens = 0;
        $totalOutputTokens = 0;

        foreach ($results as $result) {
            $wordCount = str_word_count($result->optimizedText);
            $totalWords += $wordCount;
            $totalCostCents += $result->costCents;
            $totalLatencyMs += $result->latencyMs;
            $totalInputTokens += $result->inputTokens;
            $totalOutputTokens += $result->outputTokens;

            if ($result->fromCache) {
                $cacheHits++;
            }

            $successes[] = [
                'id' => $result->id,
                'source_text' => $result->sourceText,
                'optimized_text' => $result->optimizedText,
                'locale' => $result->targetLocale,
                'type' => $result->optimizationType->value,
                'before_score' => $result->score->beforeScore,
                'after_score' => $result->score->afterScore,
                'improvement' => $result->score->improvement,
                'cost_cents' => $result->costCents,
                'latency_ms' => $result->latencyMs,
                'from_cache' => $result->fromCache,
            ];
        }

        $resultCount = count($results);

        return [
            'summary' => [
                'total' => $resultCount,
                'successful' => count($successes),
                'failed' => count($failures),
                'cache_hits' => $cacheHits,
                'cache_hit_rate' => $resultCount > 0
                    ? round($cacheHits / $resultCount, 4)
                    : 0.0,
                'total_words' => $totalWords,
                'total_cost_cents' => round($totalCostCents, 6),
                'cost_per_word_cents' => $totalWords > 0
                    ? round($totalCostCents / $totalWords, 6)
                    : 0.0,
                'total_input_tokens' => $totalInputTokens,
                'total_output_tokens' => $totalOutputTokens,
                'avg_latency_ms' => $resultCount > 0
                    ? (int) round($totalLatencyMs / $resultCount)
                    : 0,
            ],
            'details' => $successes,
            'failures' => $failures,
        ];
    }

    /**
     * Add a failed item to the aggregator result.
     */
    public static function failureEntry(int $index, string $sourceText, string $locale, string $type, string $error): array
    {
        return [
            'index' => $index,
            'source_text' => $sourceText,
            'locale' => $locale,
            'type' => $type,
            'error' => $error,
        ];
    }
}
