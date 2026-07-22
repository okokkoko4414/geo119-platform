<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Translation;
use App\Services\ClaudeLocal\ClaudeLocalClient;
use App\Services\QualityGate;
use App\Services\TranslationCache;
use App\Services\TranslationManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class TranslateStringJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public readonly string $locale,
        public readonly string $namespace,
        public readonly string $key,
        public readonly string $sourceText,
        public readonly int $tier,
    ) {}

    public function handle(
        ClaudeLocalClient $ai,
        TranslationCache $cache,
        QualityGate $quality,
        TranslationManager $manager,
    ): void {
        // 1. Dedup check
        if ($cache->has($this->locale, $this->namespace, $this->key)) {
            return;
        }

        // 2. Preprocess: extract HTML tags, ICU placeholders, Laravel placeholders
        [$cleanText, $placeholders] = $manager->preprocessValue($this->sourceText);

        // 3. AI translation via ClaudeLocalClient (with circuit breaker, rate limiting, cost tracking)
        try {
            $result = $ai->translate(
                source: $cleanText,
                targetLocale: $this->locale,
                context: $this->getContext(),
            );
            $translatedValue = $result['content'] ?? '';
        } catch (\RuntimeException $e) {
            Log::warning('TranslateStringJob: AI translation failed', [
                'locale' => $this->locale,
                'namespace' => $this->namespace,
                'key' => $this->key,
                'error' => $e->getMessage(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $this->persistFallback($this->sourceText, 0.0);

                return;
            }

            $this->release($this->backoff * (2 ** ($this->attempts() - 1)));

            return;
        }

        if ($translatedValue === '') {
            Log::warning('TranslateStringJob: empty translation returned', [
                'locale' => $this->locale,
                'key' => $this->key,
            ]);
            $this->release($this->backoff * (2 ** ($this->attempts() - 1)));

            return;
        }

        // 4. Reinsert placeholders
        $translatedValue = $manager->reinsertPlaceholders($translatedValue, $placeholders);

        // 5. Validate placeholder integrity
        if (! $manager->validatePlaceholders($this->sourceText, $translatedValue, $placeholders)) {
            Log::warning('TranslateStringJob: placeholder validation failed', [
                'locale' => $this->locale,
                'key' => $this->key,
            ]);

            if ($this->attempts() >= $this->tries) {
                $this->persistFallback($translatedValue, 0.0);

                return;
            }

            $this->release($this->backoff * (2 ** ($this->attempts() - 1)));

            return;
        }

        // 6. Quality scoring
        $score = $quality->score($translatedValue, $this->sourceText, $this->locale);

        if ($quality->isHallucination($score) && $this->attempts() < $this->tries) {
            Log::warning('TranslateStringJob: hallucination detected, retrying', [
                'locale' => $this->locale,
                'key' => $this->key,
                'score' => $score,
            ]);
            $this->release($this->backoff * (2 ** ($this->attempts() - 1)));

            return;
        }

        // 7. Persist (transactional)
        DB::transaction(function () use ($translatedValue, $score): void {
            Translation::updateOrCreate(
                [
                    'locale' => $this->locale,
                    'namespace' => $this->namespace,
                    'key' => $this->key,
                ],
                [
                    'value' => $translatedValue,
                    'source_value' => $this->sourceText,
                    'quality_score' => $score,
                    'is_machine_translated' => true,
                    'is_verified' => $score >= 0.85,
                ]
            );
        });

        // 8. Cache warm
        $cache->warm($this->locale, $this->namespace, $this->key, $translatedValue);

        // 9. Sync to JSON file for file-based serving path
        $this->syncToJsonFile($translatedValue);
    }

    private function syncToJsonFile(string $value): void
    {
        $dir = base_path("lang/{$this->locale}");
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = "{$dir}/{$this->namespace}.json";
        $existing = [];
        if (file_exists($path)) {
            $content = file_get_contents($path);
            if ($content !== false) {
                $existing = json_decode($content, true) ?? [];
            }
        }

        if (($existing[$this->key] ?? null) !== $value) {
            $existing[$this->key] = $value;
            file_put_contents(
                $path,
                json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
            );
        }
    }

    private function persistFallback(string $fallbackValue, float $score): void
    {
        DB::transaction(function () use ($fallbackValue, $score): void {
            Translation::updateOrCreate(
                [
                    'locale' => $this->locale,
                    'namespace' => $this->namespace,
                    'key' => $this->key,
                ],
                [
                    'value' => $fallbackValue,
                    'source_value' => $this->sourceText,
                    'quality_score' => $score,
                    'is_machine_translated' => false,
                    'is_verified' => false,
                ]
            );
        });
    }

    private function getContext(): string
    {
        $surrounding = Translation::locale('en')
            ->namespace($this->namespace)
            ->where('key', '!=', $this->key)
            ->inRandomOrder()
            ->limit(3)
            ->pluck('key')
            ->toArray();

        return implode(', ', $surrounding);
    }

    /** @return string[] */
    public function tags(): array
    {
        return [
            "locale:{$this->locale}",
            "tier:{$this->tier}",
            "namespace:{$this->namespace}",
        ];
    }
}
