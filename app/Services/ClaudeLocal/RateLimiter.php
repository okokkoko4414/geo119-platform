<?php

declare(strict_types=1);

namespace App\Services\ClaudeLocal;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;

class RateLimiter
{
    private const KEY_TOKENS = 'rate_limiter:%s:tokens';
    private const KEY_REFFILL = 'rate_limiter:%s:last_refill';

    private string $name;

    private float $maxTokens;

    private float $refillRate; // tokens per second

    private Connection $redis;

    public function __construct(
        string $name = 'default',
        int $maxTokens = 500,
        float $refillRate = 100.0 / 60.0,
    ) {
        $this->name = $name;
        $this->maxTokens = (float) $maxTokens;
        $this->refillRate = $refillRate;
        $this->redis = Redis::connection('cache');
    }

    /**
     * Atomically acquire a token using a Redis-backed token bucket.
     */
    public function tryAcquire(): bool
    {
        $tokensKey = sprintf(self::KEY_TOKENS, $this->name);
        $refillKey = sprintf(self::KEY_REFFILL, $this->name);

        $script = <<<'LUA'
            local tokens = redis.call('GET', KEYS[1])
            local max_tokens = tonumber(ARGV[1])
            local refill_rate = tonumber(ARGV[2])
            local now = tonumber(ARGV[3])

            if tokens == false then
                tokens = max_tokens
            else
                tokens = tonumber(tokens)
                local last_refill_str = redis.call('GET', KEYS[2])
                local last_refill = tonumber(last_refill_str or now)
                local elapsed = math.max(0, now - last_refill)
                tokens = math.min(max_tokens, tokens + elapsed * refill_rate)
            end

            if tokens >= 1 then
                redis.call('SET', KEYS[1], tokens - 1, 'EX', 3600)
                redis.call('SET', KEYS[2], now, 'EX', 3600)
                return 1
            end

            redis.call('SET', KEYS[1], tokens, 'EX', 3600)
            redis.call('SET', KEYS[2], now, 'EX', 3600)
            return 0
        LUA;

        $result = $this->redis->eval(
            $script,
            2,
            $tokensKey,
            $refillKey,
            (string) $this->maxTokens,
            (string) $this->refillRate,
            (string) microtime(true),
        );

        return $result === 1 || $result === '1';
    }

    public function getAvailableTokens(): float
    {
        $tokensKey = sprintf(self::KEY_TOKENS, $this->name);
        $refillKey = sprintf(self::KEY_REFFILL, $this->name);

        $tokens = $this->redis->get($tokensKey);
        $lastRefill = $this->redis->get($refillKey);

        if ($tokens === null) {
            return $this->maxTokens;
        }

        $tokens = (float) $tokens;
        $lastRefill = (float) ($lastRefill ?? microtime(true));
        $elapsed = microtime(true) - $lastRefill;

        return min($this->maxTokens, $tokens + $elapsed * $this->refillRate);
    }
}
