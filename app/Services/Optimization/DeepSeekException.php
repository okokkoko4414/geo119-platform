<?php

declare(strict_types=1);

namespace App\Services\Optimization;

use RuntimeException;

class DeepSeekException extends RuntimeException
{
    public static function timeout(int $latencyMs): self
    {
        return new self("DeepSeek request timed out after {$latencyMs}ms");
    }

    public static function serverError(int $httpStatus): self
    {
        return new self("DeepSeek returned HTTP {$httpStatus}");
    }

    public static function partialResponse(array $missingSegments): self
    {
        $ids = implode(', ', $missingSegments);
        return new self("DeepSeek returned partial response — missing segments: {$ids}");
    }

    public static function connectionFailure(string $message): self
    {
        return new self("DeepSeek connection failure: {$message}");
    }
}
