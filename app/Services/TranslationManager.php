<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\TranslateStringJob;
use App\Models\Language;
use App\Models\Translation;
use Illuminate\Support\Facades\Log;

final class TranslationManager
{
    /**
     * @param  array<string, string>  $strings  key => english source text
     */
    public function expandLanguage(string $code, array $strings, string $namespace = 'ui'): int
    {
        $language = $this->registry()->activate($code);
        $dispatched = 0;

        foreach ($strings as $key => $sourceValue) {
            $this->dispatchTranslationJob($language, $namespace, $key, $sourceValue);
            $dispatched++;
        }

        return $dispatched;
    }

    public function retranslateKey(string $locale, string $namespace, string $key, string $sourceValue): void
    {
        $language = $this->registry()->findActiveByCode($locale);
        if ($language === null) {
            Log::warning('TranslationManager: language not active', ['locale' => $locale]);

            return;
        }

        $this->cache()->forget($locale, $namespace, $key);
        $this->dispatchTranslationJob($language, $namespace, $key, $sourceValue);
    }

    public function retranslateLocale(string $locale, string $namespace = 'ui'): int
    {
        $language = $this->registry()->findActiveByCode($locale);
        if ($language === null) {
            return 0;
        }

        $this->cache()->forgetLocale($locale);

        $sourceTranslations = Translation::locale('en')->namespace($namespace)->get();
        $dispatched = 0;

        foreach ($sourceTranslations as $source) {
            $this->dispatchTranslationJob($language, $namespace, $source->key, $source->value);
            $dispatched++;
        }

        return $dispatched;
    }

    /**
     * Preprocess: extract HTML tags and ICU placeholders, translate text, reinsert.
     */
    public function preprocessValue(string $value): array
    {
        $placeholders = [];

        // Extract Laravel placeholders first: :name, :count
        // Must run before HTML/ICU because _ in token names is \w, so
        // :\w+ would greedily consume through ___HTML_N___ / ___ICU_N___ tokens.
        $value = (string) preg_replace_callback(
            '/:\w+/',
            function (array $matches) use (&$placeholders): string {
                $token = '___PH_'.count($placeholders).'___';
                $placeholders[$token] = $matches[0];

                return $token;
            },
            $value
        );

        // Extract ICU MessageFormat syntax: {param, select, ...} or {count}
        $value = (string) preg_replace_callback(
            '/\{[^}]+\}/',
            function (array $matches) use (&$placeholders): string {
                $token = '___ICU_'.count($placeholders).'___';
                $placeholders[$token] = $matches[0];

                return $token;
            },
            $value
        );

        // Extract HTML tags last (they can wrap other placeholder types)
        $value = (string) preg_replace_callback(
            '/<[^>]+>/',
            function (array $matches) use (&$placeholders): string {
                $token = '___HTML_'.count($placeholders).'___';
                $placeholders[$token] = $matches[0];

                return $token;
            },
            $value
        );

        return [$value, $placeholders];
    }

    public function reinsertPlaceholders(string $translated, array $placeholders): string
    {
        return str_replace(
            array_keys($placeholders),
            array_values($placeholders),
            $translated
        );
    }

    public function validatePlaceholders(string $original, string $translated, array $placeholders): bool
    {
        foreach ($placeholders as $token => $originalValue) {
            if (! str_contains($translated, $originalValue)) {
                return false;
            }
        }

        return true;
    }

    public function warmCache(string $locale): int
    {
        return $this->cache()->warmFromDatabase($locale);
    }

    private function dispatchTranslationJob(
        Language $language,
        string $namespace,
        string $key,
        string $sourceValue
    ): void {
        $queue = match ($language->tier) {
            1 => 'translations-tier1',
            2 => 'translations-tier2',
            3 => 'translations-tier3',
            default => 'translations-tier2',
        };

        TranslateStringJob::dispatch(
            locale: $language->code,
            namespace: $namespace,
            key: $key,
            sourceText: $sourceValue,
            tier: $language->tier,
        )->onQueue($queue);
    }

    private function registry(): LanguageRegistry
    {
        return app(LanguageRegistry::class);
    }

    private function cache(): TranslationCache
    {
        return app(TranslationCache::class);
    }
}
