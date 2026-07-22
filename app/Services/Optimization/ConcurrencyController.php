<?php

declare(strict_types=1);

namespace App\Services\Optimization;

use App\Services\Contracts\RedisStore;

final class ConcurrencyController
{
    private const SLOTS_KEY = 'concurrency:slots';
    private const INITIALIZED_KEY = 'concurrency:initialized';

    public function __construct(
        private readonly RedisStore $redis,
        private readonly int $maxConcurrent = 10,
    ) {}

    /**
     * Initialize the semaphore counter. Idempotent — only sets if not already initialized.
     */
    public function initialize(): void
    {
        if (!$this->redis->setnx(self::INITIALIZED_KEY, '1', 0)) {
            return;
        }
        $this->redis->set(self::SLOTS_KEY, (string) $this->maxConcurrent);
    }

    public function acquire(): bool
    {
        $newValue = $this->redis->decr(self::SLOTS_KEY);

        if ($newValue < 0) {
            // Over-subscribed — roll back the decrement.
            // Slot may be lost if we crash here, but reconcile() handles drift.
            $this->redis->incr(self::SLOTS_KEY);
            return false;
        }

        return true;
    }

    public function release(): void
    {
        $current = (int) $this->redis->get(self::SLOTS_KEY);
        if ($current < $this->maxConcurrent) {
            $this->redis->incr(self::SLOTS_KEY);
        }
    }

    public function availableSlots(): int
    {
        return max(0, (int) ($this->redis->get(self::SLOTS_KEY) ?: $this->maxConcurrent));
    }

    /**
     * Reconcile counter drift — called periodically or on pod start.
     * Resets the semaphore to max concurrent, accounting for any drift
     * from crashed workers that didn't release their slot.
     */
    public function reconcile(): void
    {
        $this->redis->set(self::SLOTS_KEY, (string) $this->maxConcurrent);
        $this->redis->set(self::INITIALIZED_KEY, '1');
    }
}
