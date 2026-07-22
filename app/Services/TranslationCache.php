<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Translation;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;

class TranslationCache
{
    private const TTL_SECONDS = 30 * 24 * 60 * 60; // 30 days

    private const PREFIX = 'trans:';

    private const STATS_HITS_KEY = 'trans:stats:hits';

    private const STATS_MISSES_KEY = 'trans:stats:misses';

    private Connection $redis;

    public function __construct()
    {
        $this->redis = Redis::connection('cache');
    }

    public function get(string $locale, string $namespace, string $key): ?string
    {
        $cached = $this->redis->get($this->cacheKey($locale, $namespace, $key));

        if ($cached !== null) {
            $this->redis->incr(self::STATS_HITS_KEY);

            return (string) $cached;
        }

        $this->redis->incr(self::STATS_MISSES_KEY);

        return null;
    }

    public function has(string $locale, string $namespace, string $key): bool
    {
        $exists = (bool) $this->redis->exists($this->cacheKey($locale, $namespace, $key));

        if ($exists) {
            $this->redis->incr(self::STATS_HITS_KEY);
        } else {
            $this->redis->incr(self::STATS_MISSES_KEY);
        }

        return $exists;
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
        $pattern = self::PREFIX."{$locale}:*";
        $keys = $this->redis->keys($pattern);
        if (! empty($keys)) {
            // PhpRedis may return keys with the connection prefix included.
            // Strip the prefix so del() doesn't double-prefix.
            $prefix = (string) config('database.redis.options.prefix', '');
            if ($prefix !== '' && str_starts_with($keys[0], $prefix)) {
                $keys = array_map(
                    fn (string $key): string => substr($key, strlen($prefix)),
                    $keys
                );
            }
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
        $hits = (int) $this->redis->get(self::STATS_HITS_KEY);
        $misses = (int) $this->redis->get(self::STATS_MISSES_KEY);
        $total = $hits + $misses;

        return $total > 0 ? $hits / $total : 0.0;
    }

    public function resetStats(): void
    {
        $this->redis->del(self::STATS_HITS_KEY, self::STATS_MISSES_KEY);
    }

    private function cacheKey(string $locale, string $namespace, string $key): string
    {
        return self::PREFIX."{$locale}:{$namespace}:{$key}";
    }
}
