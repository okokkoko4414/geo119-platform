<?php

declare(strict_types=1);

namespace App\Services\Optimization;

use App\Services\Contracts\RedisStore;

final class CircuitBreaker
{
    private const STATE_KEY = 'cb:deepseek:state';

    private const OPENED_AT_KEY = 'cb:deepseek:opened_at';

    private const FAILURES_KEY = 'cb:deepseek:failures';

    private const REOPENED_KEY = 'cb:deepseek:reopened';

    private const FAILURE_THRESHOLD = 5;

    private const COOLDOWN_SECONDS = 30;

    public function __construct(
        private readonly RedisStore $redis,
        private readonly int $failureThreshold = self::FAILURE_THRESHOLD,
        private readonly int $cooldownSeconds = self::COOLDOWN_SECONDS,
    ) {}

    public function isAvailable(): bool
    {
        $state = $this->getState();

        if ($state === 'CLOSED') {
            return true;
        }

        if ($state === 'OPEN') {
            // If the circuit was just reopened by a half-open probe failure,
            // stay open regardless of cooldown
            if ($this->redis->get(self::REOPENED_KEY)) {
                return false;
            }

            $openedAt = (int) ($this->redis->get(self::OPENED_AT_KEY) ?: 0);
            if ((time() - $openedAt) >= $this->cooldownSeconds) {
                $this->redis->set(self::STATE_KEY, 'HALF_OPEN');

                return true;
            }

            return false;
        }

        // HALF_OPEN — allow probe
        return true;
    }

    public function recordSuccess(): void
    {
        $this->redis->set(self::STATE_KEY, 'CLOSED');
        $this->redis->del(self::FAILURES_KEY);
        $this->redis->del(self::OPENED_AT_KEY);
        $this->redis->del(self::REOPENED_KEY);
    }

    public function recordFailure(): void
    {
        $state = $this->getState();

        // Half-open probe failure: re-open the circuit immediately
        if ($state === 'HALF_OPEN') {
            $this->redis->set(self::STATE_KEY, 'OPEN');
            $this->redis->set(self::OPENED_AT_KEY, (string) time());
            $this->redis->set(self::REOPENED_KEY, '1');

            return;
        }

        $failures = $this->redis->incr(self::FAILURES_KEY);

        if ($failures >= $this->failureThreshold) {
            $this->redis->set(self::STATE_KEY, 'OPEN');
            $this->redis->set(self::OPENED_AT_KEY, (string) time());
        }
    }

    public function getState(): string
    {
        return $this->redis->get(self::STATE_KEY) ?: 'CLOSED';
    }

    public function getFailureCount(): int
    {
        return (int) ($this->redis->get(self::FAILURES_KEY) ?: 0);
    }

    public function retryAfterSeconds(): int
    {
        if ($this->getState() !== 'OPEN') {
            return 0;
        }
        $openedAt = (int) ($this->redis->get(self::OPENED_AT_KEY) ?: 0);
        $elapsed = time() - $openedAt;

        return max(0, $this->cooldownSeconds - $elapsed);
    }

    /**
     * Reset the circuit breaker to CLOSED state — for testing and manual intervention.
     */
    public function reset(): void
    {
        $this->redis->set(self::STATE_KEY, 'CLOSED');
        $this->redis->del(self::FAILURES_KEY);
        $this->redis->del(self::OPENED_AT_KEY);
        $this->redis->del(self::REOPENED_KEY);
    }
}
