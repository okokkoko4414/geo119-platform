<?php

declare(strict_types=1);

namespace App\Services\Optimization;

use Psr\Log\LoggerInterface;

final class RetryManager
{
    /** @var array<int, int> Retry attempt -> delay in ms */
    private const DELAYS_MS = [1000, 2000, 4000];
    private const MAX_JITTER_PCT = 0.3;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ?DeadLetterQueue $deadLetterQueue = null,
        private readonly int $maxRetries = 3,
    ) {}

    /**
     * Execute an operation with retry logic.
     *
     * @template T
     * @param callable(): T $operation
     * @return T
     * @throws DeepSeekException
     */
    public function execute(callable $operation): mixed
    {
        $attempt = 0;

        while ($attempt <= $this->maxRetries) {
            try {
                return $operation();
            } catch (DeepSeekException $e) {
                $attempt++;

                if ($attempt > $this->maxRetries) {
                    $this->logger->error('RetryManager: all retries exhausted', [
                        'attempts' => $attempt,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }

                $delayIndex = $attempt - 1;
                $baseDelay = self::DELAYS_MS[$delayIndex] ?? end(self::DELAYS_MS);
                $jitter = random_int(0, (int) ($baseDelay * self::MAX_JITTER_PCT));
                $delayUs = ($baseDelay + $jitter) * 1000;

                $this->logger->warning('RetryManager: retrying after failure', [
                    'attempt' => $attempt,
                    'max_retries' => $this->maxRetries,
                    'delay_ms' => round($delayUs / 1000, 1),
                    'error' => $e->getMessage(),
                ]);

                usleep($delayUs);
            }
        }

        // Unreachable — the loop always either returns or throws
        throw new DeepSeekException('RetryManager: unexpected state');
    }

    /**
     * Execute a batch operation with granular retry — only retry failed items.
     *
     * @template T
     * @param array<int, T> $items
     * @param callable(T): mixed $operation
     * @return array<int, array{index: int, result: mixed}>
     */
    public function executeBatch(array $items, callable $operation): array
    {
        $results = [];
        $retryQueue = [];

        foreach ($items as $index => $item) {
            try {
                $results[] = ['index' => $index, 'result' => $operation($item)];
            } catch (DeepSeekException $e) {
                $retryQueue[] = ['index' => $index, 'item' => $item, 'error' => $e];
            }
        }

        $attempt = 1;
        while ($retryQueue && $attempt <= $this->maxRetries) {
            $stillFailing = [];
            $delayIndex = $attempt - 1;
            $baseDelay = self::DELAYS_MS[$delayIndex] ?? end(self::DELAYS_MS);
            $jitter = random_int(0, (int) ($baseDelay * self::MAX_JITTER_PCT));
            usleep(($baseDelay + $jitter) * 1000);

            foreach ($retryQueue as $entry) {
                try {
                    $results[] = ['index' => $entry['index'], 'result' => $operation($entry['item'])];
                } catch (DeepSeekException $e) {
                    $stillFailing[] = ['index' => $entry['index'], 'item' => $entry['item'], 'error' => $e];
                }
            }

            $retryQueue = $stillFailing;
            $attempt++;
        }

        // Send permanently failed items to dead letter queue
        if ($this->deadLetterQueue !== null && !empty($retryQueue)) {
            foreach ($retryQueue as $entry) {
                $this->deadLetterQueue->push([
                    'index' => $entry['index'],
                    'source_text' => is_string($entry['item']) ? $entry['item'] : json_encode($entry['item']),
                    'locale' => '',
                    'type' => 'batch',
                    'error' => $entry['error']->getMessage(),
                    'attempts' => $this->maxRetries,
                ]);
            }
        }

        return $results;
    }
}
