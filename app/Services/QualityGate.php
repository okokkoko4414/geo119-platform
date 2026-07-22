<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Language;
use App\Models\Translation;
use App\Services\ClaudeLocal\ClaudeLocalClient;
use Illuminate\Support\Facades\Log;

final class QualityGate
{
    private const HALLUCINATION_THRESHOLD = 0.3;

    private const REGRESSION_DELTA_MAX = 0.02; // 2%

    public function __construct(
        private readonly LanguageRegistry $registry,
        private readonly ClaudeLocalClient $ai,
    ) {}

    /**
     * Score a translation using COMET-like evaluation via DeepSeek.
     * Returns 0.0 - 1.0 where higher is better.
     */
    public function score(string $translation, string $source, string $locale): float
    {
        if ($translation === '' || $source === '') {
            return 0.0;
        }

        try {
            $prompt = <<<PROMPT
Rate the quality of this translation on a scale from 0.0 to 1.0, where 1.0 is perfect.

Source (English): {$source}
Translation ({$locale}): {$translation}

Consider:
1. Semantic accuracy (does it mean the same thing?)
2. Fluency (does it read naturally in the target language?)
3. Terminology (are domain-specific terms correct?)

Respond with ONLY a number between 0.0 and 1.0, nothing else.
PROMPT;

            $result = $this->ai->chat(
                messages: [['role' => 'user', 'content' => $prompt]],
                options: ['temperature' => 0.0, 'max_tokens' => 10],
            );

            $content = trim((string) ($result['content'] ?? ''));
            $score = $this->extractScore($content);

            if ($score < 0.0 || $score > 1.0) {
                return 0.5;
            }

            return $score;
        } catch (\RuntimeException $e) {
            Log::warning('QualityGate: AI scoring failed', [
                'locale' => $locale,
                'error' => $e->getMessage(),
            ]);

            return 0.5;
        }
    }

    public function isHallucination(float $score): bool
    {
        return $score < self::HALLUCINATION_THRESHOLD;
    }

    public function languagePasses(Language $language): bool
    {
        $threshold = $this->thresholdForLanguage($language);

        return $language->quality_score !== null
            && $language->quality_score >= $threshold;
    }

    public function thresholdForLanguage(Language $language): float
    {
        return match ($language->tier) {
            1 => $this->registry->getQualityThreshold(1),
            2 => ($language->baseline_score ?? 0.85) * 0.8,
            3 => 0.70,
            default => 0.80,
        };
    }

    /**
     * @return array{language: string, tier: int, average_score: float, threshold: float, total_translations: int, below_threshold: int, hallucinations: int, passes: bool}
     */
    public function evaluateLanguage(Language $language): array
    {
        $translations = Translation::locale($language->code)->get();

        if ($translations->isEmpty()) {
            return [
                'language' => $language->code,
                'tier' => $language->tier,
                'average_score' => 0.0,
                'threshold' => $this->thresholdForLanguage($language),
                'total_translations' => 0,
                'below_threshold' => 0,
                'hallucinations' => 0,
                'passes' => false,
            ];
        }

        $scores = $translations->map(fn (Translation $t): float => $t->quality_score ?? 0.0);

        $average = $scores->avg();
        $threshold = $this->thresholdForLanguage($language);

        return [
            'language' => $language->code,
            'tier' => $language->tier,
            'average_score' => round($average, 4),
            'threshold' => $threshold,
            'total_translations' => $translations->count(),
            'below_threshold' => $scores->filter(fn (float $s): bool => $s < $threshold)->count(),
            'hallucinations' => $scores->filter(fn (float $s): bool => $s < self::HALLUCINATION_THRESHOLD)->count(),
            'passes' => $average >= $threshold,
        ];
    }

    /**
     * Regression test: re-score existing 25 languages and assert <=2% quality delta.
     * Returns array of regressions (empty = pass).
     */
    public function regressionTest(): array
    {
        $baselineCodes = $this->registry->getBaselineLanguages();
        $regressions = [];

        foreach ($baselineCodes as $code) {
            $lang = $this->registry->findByCode($code);
            if ($lang === null || $lang->baseline_score === null) {
                continue;
            }

            $currentScore = $this->computeLanguageScore($lang);
            $delta = $currentScore - $lang->baseline_score;

            if ($delta < -self::REGRESSION_DELTA_MAX) {
                $regressions[] = [
                    'language' => $code,
                    'baseline' => $lang->baseline_score,
                    'current' => $currentScore,
                    'delta' => round($delta, 4),
                    'delta_percent' => round(($delta / $lang->baseline_score) * 100, 2),
                ];
            }

            $lang->update(['quality_score' => $currentScore]);
        }

        return $regressions;
    }

    public function computeLanguageScore(Language $language): float
    {
        $translations = Translation::locale($language->code)
            ->whereNotNull('quality_score')
            ->get();

        if ($translations->isEmpty()) {
            return 0.0;
        }

        return $translations->avg('quality_score');
    }

    private function extractScore(string $raw): float
    {
        if (preg_match('/([0-9]?\.[0-9]+|[01]\.0|[01])/', $raw, $matches)) {
            return (float) $matches[1];
        }

        return 0.5;
    }

    public function needsHumanReview(float $score): bool
    {
        return $score < self::HALLUCINATION_THRESHOLD;
    }

    public function coverage(string $locale): float
    {
        $totalSourceKeys = Translation::locale('en')
            ->where('namespace', 'ui')
            ->count();

        if ($totalSourceKeys === 0) {
            return 1.0;
        }

        $translatedKeys = Translation::locale($locale)
            ->where('namespace', 'ui')
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->count();

        return $translatedKeys / $totalSourceKeys;
    }

    /**
     * Full quality report for all active languages.
     */
    public function fullReport(): array
    {
        $languages = $this->registry->active();
        $report = [];

        foreach ($languages as $lang) {
            $report[] = $this->evaluateLanguage($lang);
        }

        $tier1Scores = collect($report)
            ->filter(fn (array $r): bool => ($r['tier'] ?? 0) === 1)
            ->pluck('average_score');

        return [
            'generated_at' => now()->toIso8601String(),
            'total_languages' => $languages->count(),
            'languages' => $report,
            'tier1_average' => $tier1Scores->avg(),
            'tier1_pass_rate' => $tier1Scores->filter(fn (float $s): bool => $s >= 0.85)->count()
                .' / '.$tier1Scores->count(),
            'regressions' => $this->regressionTest(),
        ];
    }
}
