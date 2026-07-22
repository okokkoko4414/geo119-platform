<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Translation;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;

final class TranslationCache
{
    private const TTL_SECONDS = 30 * 24 * 60 * 60; // 30 days
    private const PREFIX = 'trans:';

    private Connection $redis;

    public function __construct()
    {
        $this->redis = Redis::connection('cache');
    }

    public function get(string $locale, string $namespace, string $key): ?string
    {
        $cached = $this->redis->get($this->cacheKey($locale, $namespace, $key));

        return $cached !== null ? (string) $cached : null;
    }

    public function has(string $locale, string $namespace, string $key): bool
    {
        return (bool) $this->redis->exists($this->cacheKey($locale, $namespace, $key));
    }

    public function put(string $locale, string $namespace, string $key, string $value): void
    {
        $this->redis->setex(
            $this->cacheKey($locale, $namespace, $key),
            self::TTL_SECONDS,
            $value
        );
    }

    public function forget(string $locale, string $namespace, string $key): void
    {
        $this->redis->del($this->cacheKey($locale, $namespace, $key));
    }

    public function forgetLocale(string $locale): void
    {
        $pattern = self::PREFIX . "{$locale}:*";
        $keys = $this->redis->keys($pattern);
        if (! empty($keys)) {
            $this->redis->del(...$keys);
        }
    }

    public function warm(string $locale, string $namespace, string $key, string $value): void
    {
        $this->put($locale, $namespace, $key, $value);
    }

    public function warmFromDatabase(string $locale): int
    {
        $count = 0;
        Translation::locale($locale)->chunk(500, function ($translations) use (&$count): void {
            foreach ($translations as $translation) {
                $this->put(
                    $translation->locale,
                    $translation->namespace,
                    $translation->key,
                    $translation->value
                );
                $count++;
            }
        });

        return $count;
    }

    public function hitRate(): float
    {
        $info = $this->redis->info('stats');
        $hits = (int) ($info['keyspace_hits'] ?? 0);
        $misses = (int) ($info['keyspace_misses'] ?? 0);
        $total = $hits + $misses;

        return $total > 0 ? $hits / $total : 0.0;
    }

    private function cacheKey(string $locale, string $namespace, string $key): string
    {
        return self::PREFIX . "{$locale}:{$namespace}:{$key}";
    }
}
