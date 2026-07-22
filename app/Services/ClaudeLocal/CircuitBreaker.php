<?php

namespace App\Services\ClaudeLocal;

class CircuitBreaker
{
    private int $failureCount = 0;
    private int $failureThreshold;
    private int $openDurationSeconds;
    private ?int $openedAt = null;

    public function __construct(int $failureThreshold = 5, int $openDurationSeconds = 30)
    {
        $this->failureThreshold = $failureThreshold;
        $this->openDurationSeconds = $openDurationSeconds;
    }

    public function isOpen(): bool
    {
        if ($this->openedAt === null) {
            return false;
        }

        if (time() - $this->openedAt >= $this->openDurationSeconds) {
            $this->openedAt = null;
            $this->failureCount = 0;

            return false; // half-open — allow probe
        }

        return true;
    }

    public function recordSuccess(): void
    {
        $this->failureCount = 0;
        $this->openedAt = null;
    }

    public function recordFailure(): void
    {
        $this->failureCount++;

        if ($this->failureCount >= $this->failureThreshold) {
            $this->openedAt = time();
        }
    }

    public function getState(): string
    {
        if ($this->openedAt === null) {
            return 'closed';
        }

        if ($this->isOpen()) {
            return 'open';
        }

        return 'half-open';
    }

    public function getFailureCount(): int
    {
        return $this->failureCount;
    }
}
