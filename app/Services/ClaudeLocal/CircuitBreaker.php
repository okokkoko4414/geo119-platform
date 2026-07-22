<?php

declare(strict_types=1);

namespace App\Services\ClaudeLocal;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;

class CircuitBreaker
{
    private const KEY_FAILURES = 'circuit_breaker:%s:failures';
    private const KEY_OPENED = 'circuit_breaker:%s:opened_at';

    private string $name;

    private int $failureThreshold;

    private int $openDurationSeconds;

    private Connection $redis;

    public function __construct(
        string $name = 'default',
        int $failureThreshold = 5,
        int $openDurationSeconds = 30,
    ) {
        $this->name = $name;
        $this->failureThreshold = $failureThreshold;
        $this->openDurationSeconds = $openDurationSeconds;
        $this->redis = Redis::connection('cache');
    }

    public function isOpen(): bool
    {
        $openedAt = $this->redis->get(sprintf(self::KEY_OPENED, $this->name));

        if ($openedAt === null) {
            return false;
        }

        if (time() - (int) $openedAt >= $this->openDurationSeconds) {
            return false; // half-open — allow probe (does not mutate state)
        }

        return true;
    }

    public function recordSuccess(): void
    {
        $this->redis->del(
            sprintf(self::KEY_FAILURES, $this->name),
            sprintf(self::KEY_OPENED, $this->name),
        );
    }

    public function recordFailure(): void
    {
        $failuresKey = sprintf(self::KEY_FAILURES, $this->name);
        $openedKey = sprintf(self::KEY_OPENED, $this->name);

        $openedAt = $this->redis->get($openedKey);

        // If in half-open state (openedAt is set but cooldown has expired),
        // a failure immediately reopens the circuit
        if ($openedAt !== null && ! $this->isOpen()) {
            $this->redis->set($openedKey, time());
            $this->redis->set($failuresKey, 1);

            return;
        }

        $failures = $this->redis->incr($failuresKey);
        $this->redis->expire($failuresKey, $this->openDurationSeconds * 2);

        if ($failures >= $this->failureThreshold) {
            $this->redis->set($openedKey, time());
            $this->redis->expire($openedKey, $this->openDurationSeconds * 2);
        }
    }

    public function getState(): string
    {
        $openedAt = $this->redis->get(sprintf(self::KEY_OPENED, $this->name));

        if ($openedAt === null) {
            return 'closed';
        }

        if ($this->isOpen()) {
            return 'open';
        }

        return 'half-open';
    }

    public function getFailureCount(): int
    {
        return (int) $this->redis->get(sprintf(self::KEY_FAILURES, $this->name));
    }
}
