<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Optimization;

use App\Services\Contracts\RedisStore;

final class FakeRedisStore implements RedisStore
{
    /** @var array<string, string> */
    private array $store = [];

    /** @var array<string, int> */
    private array $ttl = [];

    /** @var array<string, int> */
    private array $expiry = [];

    /** @var array<string, string[]> */
    private array $lists = [];

    public function get(string $key): ?string
    {
        $this->evictExpired($key);
        return $this->store[$key] ?? null;
    }

    public function set(string $key, string $value, int $ttl = 0): void
    {
        $this->store[$key] = $value;
        if ($ttl > 0) {
            $this->ttl[$key] = $ttl;
            $this->expiry[$key] = time() + $ttl;
        }
    }

    public function setex(string $key, int $ttl, string $value): void
    {
        $this->set($key, $value, $ttl);
    }

    public function setnx(string $key, string $value, int $ttl = 0): bool
    {
        $this->evictExpired($key);
        if (isset($this->store[$key])) {
            return false;
        }
        $this->set($key, $value, $ttl);
        return true;
    }

    public function del(string ...$keys): int
    {
        $count = 0;
        foreach ($keys as $key) {
            if (isset($this->store[$key])) {
                unset($this->store[$key], $this->ttl[$key], $this->expiry[$key]);
                $count++;
            }
            if (isset($this->lists[$key])) {
                unset($this->lists[$key]);
                $count++;
            }
        }
        return $count;
    }

    public function exists(string $key): bool
    {
        $this->evictExpired($key);
        return isset($this->store[$key]) || isset($this->lists[$key]);
    }

    public function incr(string $key): int
    {
        $current = (int) ($this->store[$key] ?? 0);
        $current++;
        $this->store[$key] = (string) $current;
        return $current;
    }

    public function decr(string $key): int
    {
        $current = (int) ($this->store[$key] ?? 0);
        $current--;
        $this->store[$key] = (string) $current;
        return $current;
    }

    public function incrbyfloat(string $key, float $increment): float
    {
        $current = (float) ($this->store[$key] ?? 0.0);
        $current += $increment;
        $this->store[$key] = (string) $current;
        return $current;
    }

    public function lpush(string $key, string $value): int
    {
        $this->lists[$key][] = $value;
        return count($this->lists[$key]);
    }

    public function lrange(string $key, int $start, int $stop): array
    {
        $list = $this->lists[$key] ?? [];

        if (empty($list)) {
            return [];
        }

        $len = count($list);
        $start = $start < 0 ? max(0, $len + $start) : $start;
        $stop = $stop < 0 ? $len + $stop : $stop;
        $stop = min($stop, $len - 1);

        if ($start > $stop) {
            return [];
        }

        return array_slice($list, $start, $stop - $start + 1);
    }

    public function lrem(string $key, int $count, string $value): int
    {
        if (!isset($this->lists[$key])) {
            return 0;
        }

        $removed = 0;
        $list = $this->lists[$key];

        if ($count === 0) {
            // Remove all
            $list = array_values(array_filter($list, fn($v) => $v !== $value));
            $removed = count($this->lists[$key]) - count($list);
        } elseif ($count > 0) {
            // Remove from head
            $newList = [];
            foreach ($list as $v) {
                if ($v === $value && $removed < $count) {
                    $removed++;
                } else {
                    $newList[] = $v;
                }
            }
            $list = $newList;
        } else {
            // Remove from tail
            $absCount = abs($count);
            $newList = [];
            $found = 0;
            foreach (array_reverse($list) as $v) {
                if ($v === $value && $found < $absCount) {
                    $found++;
                    $removed++;
                } else {
                    $newList[] = $v;
                }
            }
            $list = array_reverse($newList);
        }

        $this->lists[$key] = $list;
        return $removed;
    }

    public function llen(string $key): int
    {
        return count($this->lists[$key] ?? []);
    }

    private function evictExpired(string $key): void
    {
        if (isset($this->expiry[$key]) && time() >= $this->expiry[$key]) {
            unset($this->store[$key], $this->ttl[$key], $this->expiry[$key]);
        }
    }
}
