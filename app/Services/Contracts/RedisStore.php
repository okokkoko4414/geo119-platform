<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface RedisStore
{
    public function get(string $key): ?string;

    public function set(string $key, string $value, int $ttl = 0): void;

    public function setex(string $key, int $ttl, string $value): void;

    public function setnx(string $key, string $value, int $ttl = 0): bool;

    public function del(string ...$keys): int;

    public function exists(string $key): bool;

    public function incr(string $key): int;

    public function decr(string $key): int;

    public function incrbyfloat(string $key, float $increment): float;

    public function lpush(string $key, string $value): int;

    public function lrange(string $key, int $start, int $stop): array;

    public function lrem(string $key, int $count, string $value): int;

    public function llen(string $key): int;
}
