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
}
