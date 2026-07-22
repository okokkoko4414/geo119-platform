<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Contracts\RedisStore;
use App\Services\Optimization\BatchOptimizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class BatchOptimizeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        private readonly string $jobId,
        private readonly array $items,
    ) {}

    public function handle(BatchOptimizer $optimizer, RedisStore $redis): void
    {
        $startTime = microtime(true);

        Log::info('BatchOptimizeJob: started', [
            'job_id' => $this->jobId,
            'item_count' => count($this->items),
        ]);

        $redis->setex(
            "batch:job:{$this->jobId}:status",
            3600,
            json_encode(['status' => 'processing', 'started_at' => date('c')])
        );

        try {
            $result = $optimizer->process($this->items);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $itemCount = count($this->items);
            $throughputPerHour = $durationMs > 0
                ? (int) round(($itemCount / $durationMs) * 3600_000)
                : 0;

            $result['meta'] = [
                'job_id' => $this->jobId,
                'item_count' => $itemCount,
                'duration_ms' => $durationMs,
                'throughput_per_hour' => $throughputPerHour,
                'completed_at' => date('c'),
                'status' => 'completed',
            ];

            $redis->setex(
                "batch:job:{$this->jobId}:result",
                3600,
                json_encode($result)
            );

            $redis->setex(
                "batch:job:{$this->jobId}:status",
                3600,
                json_encode([
                    'status' => 'completed',
                    'duration_ms' => $durationMs,
                    'throughput_per_hour' => $throughputPerHour,
                    'completed_at' => date('c'),
                ])
            );

            Log::info('BatchOptimizeJob: completed', [
                'job_id' => $this->jobId,
                'duration_ms' => $durationMs,
                'throughput_per_hour' => $throughputPerHour,
                'successful' => $result['summary']['successful'] ?? 0,
                'failed' => $result['summary']['failed'] ?? 0,
                'cache_hits' => $result['summary']['cache_hits'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            Log::error('BatchOptimizeJob: failed', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
            ]);

            $redis->setex(
                "batch:job:{$this->jobId}:status",
                3600,
                json_encode([
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'failed_at' => date('c'),
                ])
            );

            throw $e;
        }
    }

    /** @return string[] */
    public function tags(): array
    {
        return [
            "batch:{$this->jobId}",
        ];
    }
}
