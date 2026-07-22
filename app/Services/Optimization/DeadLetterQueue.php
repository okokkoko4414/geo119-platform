<?php

declare(strict_types=1);

namespace App\Services\Optimization;

use App\Services\Contracts\RedisStore;
use JsonException;
use Psr\Log\LoggerInterface;

final class DeadLetterQueue
{
    private const DLQ_KEY = 'optimization:dlq';
    private const DLQ_LIST_KEY = 'optimization:dlq:list';
    private const ENTRY_TTL = 86400 * 7; // 7 days retention

    public function __construct(
        private readonly RedisStore $redis,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Add a failed item to the dead letter queue.
     *
     * @param array{index: int, source_text: string, locale: string, type: string, error: string, attempts: int} $entry
     */
    public function push(array $entry): void
    {
        $id = substr(hash('sha256', $entry['source_text'] . '|' . $entry['locale'] . '|' . $entry['type'] . '|' . microtime(true)), 0, 16);
        $dlqEntryKey = self::DLQ_KEY . ':' . $id;
        $serialized = json_encode([
            'id' => $id,
            'source_text' => $entry['source_text'],
            'locale' => $entry['locale'],
            'type' => $entry['type'],
            'error' => $entry['error'],
            'attempts' => $entry['attempts'],
            'failed_at' => date('c'),
        ], JSON_THROW_ON_ERROR);

        $this->redis->setex($dlqEntryKey, self::ENTRY_TTL, $serialized);
        $this->redis->lpush(self::DLQ_LIST_KEY, $id);

        $this->logger->warning('DeadLetterQueue: item queued after exhausted retries', [
            'dlq_id' => $id,
            'source_preview' => mb_substr($entry['source_text'], 0, 80),
            'error' => $entry['error'],
            'attempts' => $entry['attempts'],
        ]);
    }

    /**
     * Retrieve all dead letter entries.
     *
     * @return array<int, array{id: string, source_text: string, locale: string, type: string, error: string, attempts: int, failed_at: string}>
     */
    public function list(int $limit = 50): array
    {
        $ids = $this->redis->lrange(self::DLQ_LIST_KEY, 0, $limit - 1);
        $entries = [];

        foreach ($ids as $id) {
            $raw = $this->redis->get(self::DLQ_KEY . ':' . $id);
            if ($raw === null) {
                continue;
            }
            try {
                $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                $entries[] = $data;
            } catch (JsonException) {
                $this->logger->warning('DeadLetterQueue: corrupt entry', ['dlq_id' => $id]);
                $this->redis->del(self::DLQ_KEY . ':' . $id);
            }
        }

        return $entries;
    }

    /**
     * Replay an entry by removing it from DLQ and returning its data.
     * Returns null if the entry no longer exists.
     */
    public function pop(string $id): ?array
    {
        $raw = $this->redis->get(self::DLQ_KEY . ':' . $id);
        if ($raw === null) {
            return null;
        }

        $this->redis->del(self::DLQ_KEY . ':' . $id);
        $this->redis->lrem(self::DLQ_LIST_KEY, 0, $id);

        try {
            return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * Remove a specific entry from the dead letter queue.
     */
    public function remove(string $id): void
    {
        $this->redis->del(self::DLQ_KEY . ':' . $id);
        $this->redis->lrem(self::DLQ_LIST_KEY, 0, $id);
    }

    /**
     * Clear the entire dead letter queue.
     */
    public function clear(): void
    {
        $ids = $this->redis->lrange(self::DLQ_LIST_KEY, 0, -1);
        if (!empty($ids)) {
            $keyArgs = array_map(fn(string $id) => self::DLQ_KEY . ':' . $id, $ids);
            $this->redis->del(...$keyArgs);
        }
        $this->redis->del(self::DLQ_LIST_KEY);
    }

    /**
     * Count entries in the dead letter queue.
     */
    public function count(): int
    {
        return $this->redis->llen(self::DLQ_LIST_KEY);
    }
}
