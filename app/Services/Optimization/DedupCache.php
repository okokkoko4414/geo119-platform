<?php

declare(strict_types=1);

namespace App\Services\Optimization;

use App\Services\Contracts\RedisStore;

final class DedupCache
{
    private const DEFAULT_TTL = 86400; // 24h TTL per issue spec

    private const LOCK_TTL = 60;            // 60s lock for in-flight requests (covers P99 + buffer)

    private const POLL_INTERVAL_US = 100_000; // 100ms poll interval

    public function __construct(
        private readonly RedisStore $redis,
        private readonly int $ttl = self::DEFAULT_TTL,
    ) {}

    public function get(string $source, string $locale, OptimizationType $type): ?OptimizationResult
    {
        $key = $this->cacheKey($source, $locale, $type);
        $cached = $this->redis->get($key);

        if (! $cached) {
            return null;
        }

        $result = OptimizationResult::fromJson($cached);

        // Mark as fromCache=true since this was retrieved from the dedup store
        return new OptimizationResult(
            id: $result->id,
            sourceText: $result->sourceText,
            optimizedText: $result->optimizedText,
            targetLocale: $result->targetLocale,
            optimizationType: $result->optimizationType,
            score: $result->score,
            costCents: $result->costCents,
            inputTokens: $result->inputTokens,
            outputTokens: $result->outputTokens,
            model: $result->model,
            latencyMs: $result->latencyMs,
            cachedAt: $result->cachedAt,
            fromCache: true,
        );
    }

    public function set(string $source, string $locale, OptimizationType $type, OptimizationResult $result): void
    {
        $key = $this->cacheKey($source, $locale, $type);
        $this->redis->setex($key, $this->ttl, $result->toJson());
    }

    /**
     * Acquire a processing lock for concurrent identical requests.
     * Returns true if this caller should do the work, false if another caller is processing.
     */
    public function acquireLock(string $source, string $locale, OptimizationType $type): bool
    {
        $lockKey = $this->lockKey($source, $locale, $type);

        return $this->redis->setnx($lockKey, 'processing', self::LOCK_TTL);
    }

    /**
     * Poll for a result while another process is computing it.
     * Returns the cached result once available, or null if the lock TTL expires.
     */
    public function pollForResult(string $source, string $locale, OptimizationType $type, int $timeoutMs = 30_000): ?OptimizationResult
    {
        $deadline = microtime(true) + ($timeoutMs / 1000);

        while (microtime(true) < $deadline) {
            $result = $this->get($source, $locale, $type);
            if ($result !== null) {
                return $result;
            }

            $lockKey = $this->lockKey($source, $locale, $type);
            if (! $this->redis->exists($lockKey)) {
                return null;
            }

            usleep(self::POLL_INTERVAL_US);
        }

        return null;
    }

    public function releaseLock(string $source, string $locale, OptimizationType $type): void
    {
        $this->redis->del($this->lockKey($source, $locale, $type));
    }

    public function hashKey(string $source, string $locale, OptimizationType $type): string
    {
        return hash('sha256', "{$source}|{$locale}|{$type->value}");
    }

    public function cacheKey(string $source, string $locale, OptimizationType $type): string
    {
        return 'dedup:'.$this->hashKey($source, $locale, $type);
    }

    private function lockKey(string $source, string $locale, OptimizationType $type): string
    {
        return 'dedup:lock:'.$this->hashKey($source, $locale, $type);
    }
}
