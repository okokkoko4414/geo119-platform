<?php

declare(strict_types=1);

namespace App\Services\ClaudeLocal;

final readonly class ClaudeLocalResponse
{
    public function __construct(
        public string $content,
        public int $inputTokens,
        public int $outputTokens,
        public string $model,
        public int $latencyMs,
        public string $finishReason,
    ) {}
}
