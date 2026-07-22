<?php

declare(strict_types=1);

namespace App\Services\Optimization;

use DateTimeImmutable;

final readonly class OptimizationResult
{
    public function __construct(
        public string $id,
        public string $sourceText,
        public string $optimizedText,
        public string $targetLocale,
        public OptimizationType $optimizationType,
        public BeforeAfterScore $score,
        public float $costCents,
        public int $inputTokens,
        public int $outputTokens,
        public string $model,
        public int $latencyMs,
        public DateTimeImmutable $cachedAt,
        public bool $fromCache = false,
    ) {}

    public function toJson(): string
    {
        return json_encode([
            'id' => $this->id,
            'source_text' => $this->sourceText,
            'optimized_text' => $this->optimizedText,
            'target_locale' => $this->targetLocale,
            'optimization_type' => $this->optimizationType->value,
            'score' => $this->score->toArray(),
            'cost_cents' => $this->costCents,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'model' => $this->model,
            'latency_ms' => $this->latencyMs,
            'cached_at' => $this->cachedAt->format(DATE_ATOM),
            'from_cache' => $this->fromCache,
        ], JSON_THROW_ON_ERROR);
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return new self(
            id: $data['id'],
            sourceText: $data['source_text'],
            optimizedText: $data['optimized_text'],
            targetLocale: $data['target_locale'],
            optimizationType: OptimizationType::from($data['optimization_type']),
            score: new BeforeAfterScore(
                beforeScore: (float) $data['score']['before_score'],
                afterScore: (float) $data['score']['after_score'],
                improvement: (float) $data['score']['improvement'],
            ),
            costCents: (float) $data['cost_cents'],
            inputTokens: (int) $data['input_tokens'],
            outputTokens: (int) $data['output_tokens'],
            model: $data['model'],
            latencyMs: (int) $data['latency_ms'],
            cachedAt: new DateTimeImmutable($data['cached_at']),
            fromCache: (bool) ($data['from_cache'] ?? false),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source_text' => $this->sourceText,
            'optimized_text' => $this->optimizedText,
            'target_locale' => $this->targetLocale,
            'optimization_type' => $this->optimizationType->value,
            'score' => $this->score->toArray(),
            'cost_cents' => $this->costCents,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'model' => $this->model,
            'latency_ms' => $this->latencyMs,
            'cached_at' => $this->cachedAt->format(DATE_ATOM),
            'from_cache' => $this->fromCache,
        ];
    }
}
