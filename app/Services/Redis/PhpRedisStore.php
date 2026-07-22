<?php

declare(strict_types=1);

namespace App\Services\Redis;

use App\Services\Contracts\RedisStore;
use Illuminate\Support\Facades\Redis;

final class PhpRedisStore implements RedisStore
{
    public function get(string $key): ?string
    {
        $value = Redis::get($key);

        return $value === false ? null : $value;
    }

    public function set(string $key, string $value, int $ttl = 0): void
    {
        if ($ttl > 0) {
            Redis::setex($key, $ttl, $value);
        } else {
            Redis::set($key, $value);
        }
    }

    public function setex(string $key, int $ttl, string $value): void
    {
        Redis::setex($key, $ttl, $value);
    }

    public function setnx(string $key, string $value, int $ttl = 0): bool
    {
        if ($ttl > 0) {
            $result = Redis::set($key, $value, 'EX', $ttl, 'NX');
        } else {
            $result = Redis::setnx($key, $value);
        }

        return (bool) $result;
    }

    public function del(string ...$keys): int
    {
        return Redis::del(...$keys);
    }

    public function exists(string $key): bool
    {
        return (bool) Redis::exists($key);
    }

    public function incr(string $key): int
    {
        return (int) Redis::incr($key);
    }

    public function decr(string $key): int
    {
        return (int) Redis::decr($key);
    }

    public function incrbyfloat(string $key, float $increment): float
    {
        return (float) Redis::incrbyfloat($key, $increment);
    }

    public function lpush(string $key, string $value): int
    {
        return (int) Redis::lpush($key, $value);
    }

    public function lrange(string $key, int $start, int $stop): array
    {
        return Redis::lrange($key, $start, $stop);
    }

    public function lrem(string $key, int $count, string $value): int
    {
        return (int) Redis::lrem($key, $count, $value);
    }

    public function llen(string $key): int
    {
        return (int) Redis::llen($key);
    }
}
