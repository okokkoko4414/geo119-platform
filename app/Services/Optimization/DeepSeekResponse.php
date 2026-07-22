<?php

declare(strict_types=1);

namespace App\Services\Optimization;

final readonly class DeepSeekResponse
{
    public function __construct(
        public string $optimizedText,
        public int $inputTokens,
        public int $outputTokens,
        public string $model,
        public int $latencyMs,
        public string $sourceText,
        public string $locale,
    ) {}
}
