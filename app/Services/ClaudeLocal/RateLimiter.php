<?php

namespace App\Services\ClaudeLocal;

class RateLimiter
{
    private float $tokens;

    private float $maxTokens;

    private float $refillRate; // tokens per second

    private ?float $lastRefill = null;

    public function __construct(int $maxTokens = 500, float $refillRate = 100.0 / 60.0)
    {
        $this->tokens = (float) $maxTokens;
        $this->maxTokens = (float) $maxTokens;
        $this->refillRate = $refillRate;
    }

    public function tryAcquire(): bool
    {
        $this->refill();

        if ($this->tokens >= 1.0) {
            $this->tokens -= 1.0;

            return true;
        }

        return false;
    }

    private function refill(): void
    {
        $now = microtime(true);

        if ($this->lastRefill === null) {
            $this->lastRefill = $now;

            return;
        }

        $elapsed = $now - $this->lastRefill;
        $this->tokens = min($this->maxTokens, $this->tokens + $elapsed * $this->refillRate);
        $this->lastRefill = $now;
    }

    public function getAvailableTokens(): float
    {
        $this->refill();

        return $this->tokens;
    }
}
