<?php

declare(strict_types=1);

namespace App\Services\Optimization;

final readonly class BeforeAfterScore
{
    public function __construct(
        public float $beforeScore,
        public float $afterScore,
        public float $improvement,
    ) {}

    public static function compute(string $beforeText, string $afterText, OptimizationType $type): self
    {
        $before = self::scoreText($beforeText, $type);
        $after = self::scoreText($afterText, $type);
        $improvement = $before > 0
            ? round(($after - $before) / $before, 4)
            : 0.0;

        return new self(
            beforeScore: round($before, 4),
            afterScore: round($after, 4),
            improvement: $improvement,
        );
    }

    private static function scoreText(string $text, OptimizationType $type): float
    {
        $wordCount = str_word_count($text);
        if ($wordCount === 0) {
            return 0.0;
        }

        $avgWordLen = strlen(preg_replace('/\s+/', '', $text)) / max($wordCount, 1);
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $sentenceCount = count($sentences);
        $avgSentenceLen = $sentenceCount > 0 ? $wordCount / $sentenceCount : $wordCount;

        return match ($type) {
            OptimizationType::Grammar => self::grammarScore($text, $wordCount),
            OptimizationType::Clarity => self::clarityScore($avgSentenceLen, $avgWordLen),
            OptimizationType::Tone => self::toneScore($text, $wordCount),
            OptimizationType::Conciseness => self::concisenessScore($wordCount, $avgSentenceLen, $avgWordLen),
            OptimizationType::Fluency => self::fluencyScore($avgSentenceLen, $avgWordLen),
            OptimizationType::Full => self::compositeScore($text, $wordCount, $avgSentenceLen, $avgWordLen),
        };
    }

    private static function grammarScore(string $text, int $wordCount): float
    {
        $passivePatterns = [
            '/\b(is|are|was|were|be|been|being)\s+\w+ed\b/i',
            '/\b(has|have|had)\s+been\s+\w+ed\b/i',
        ];
        $passiveCount = 0;
        foreach ($passivePatterns as $pattern) {
            $passiveCount += preg_match_all($pattern, $text);
        }
        $passiveRatio = $passiveCount / max($wordCount, 1);

        return max(0.0, 1.0 - $passiveRatio * 5);
    }

    private static function clarityScore(float $avgSentenceLen, float $avgWordLen): float
    {
        $sentenceScore = $avgSentenceLen > 0
            ? max(0.0, 1.0 - abs($avgSentenceLen - 18) / 18)
            : 0.5;
        $wordScore = $avgWordLen > 0
            ? max(0.0, 1.0 - abs($avgWordLen - 5) / 5)
            : 0.5;

        return ($sentenceScore + $wordScore) / 2;
    }

    private static function toneScore(string $text, int $wordCount): float
    {
        $positiveMarkers = preg_match_all(
            '/\b(?:clear|effective|robust|reliable|efficient|optimal|seamless|intuitive)\b/i',
            $text,
        );
        $negativeMarkers = preg_match_all(
            '/\b(?:confusing|difficult|problematic|unclear|ambiguous|inconsistent)\b/i',
            $text,
        );
        $ratio = ($positiveMarkers - $negativeMarkers) / max($wordCount, 1);

        return max(0.0, min(1.0, 0.5 + $ratio * 10));
    }

    private static function concisenessScore(int $wordCount, float $avgSentenceLen, float $avgWordLen): float
    {
        $sentenceScore = $avgSentenceLen > 0
            ? max(0.0, 1.0 - ($avgSentenceLen - 10) / 30)
            : 0.5;
        $wordScore = max(0.0, 1.0 - ($avgWordLen - 4) / 8);

        return ($sentenceScore + $wordScore) / 2;
    }

    private static function fluencyScore(float $avgSentenceLen, float $avgWordLen): float
    {
        $variation = abs($avgSentenceLen - 15) / 15 + abs($avgWordLen - 4.5) / 4.5;

        return max(0.0, 1.0 - $variation / 2);
    }

    private static function compositeScore(string $text, int $wordCount, float $avgSentenceLen, float $avgWordLen): float
    {
        $grammar = self::grammarScore($text, $wordCount);
        $clarity = self::clarityScore($avgSentenceLen, $avgWordLen);
        $tone = self::toneScore($text, $wordCount);
        $conciseness = self::concisenessScore($wordCount, $avgSentenceLen, $avgWordLen);
        $fluency = self::fluencyScore($avgSentenceLen, $avgWordLen);

        return ($grammar + $clarity + $tone + $conciseness + $fluency) / 5;
    }

    public function toArray(): array
    {
        return [
            'before_score' => $this->beforeScore,
            'after_score' => $this->afterScore,
            'improvement' => $this->improvement,
        ];
    }
}
