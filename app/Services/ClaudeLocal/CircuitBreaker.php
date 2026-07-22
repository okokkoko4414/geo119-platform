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
            return false; // half-open — allow probe (does NOT mutate state)
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
        // If in half-open state (openedAt is set but cooldown has expired),
        // a failure immediately reopens the circuit
        if ($this->openedAt !== null && ! $this->isOpen()) {
            $this->openedAt = time();
            $this->failureCount = 1;

            return;
        }

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
