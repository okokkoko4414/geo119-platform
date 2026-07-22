<?php

declare(strict_types=1);

namespace App\Services\I18n;

class TranslationLoader
{
    /**
     * Load translations for a locale and namespace.
     * Fallback chain: requested locale -> 'en' -> key string -> ''.
     *
     * @return array<string, string>
     */
    public function load(string $locale, string $namespace): array
    {
        $translations = $this->loadFile('en', $namespace);

        if ($locale !== 'en') {
            $localeTranslations = $this->loadFile($locale, $namespace);
            $translations = array_merge($translations, $localeTranslations);
        }

        return $translations;
    }

    /**
     * @return array<string, string>
     */
    private function loadFile(string $locale, string $namespace): array
    {
        $path = base_path("lang/{$locale}/{$namespace}.json");

        if (! file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return [];
        }

        /** @var array<string, string>|null $data */
        $data = json_decode($content, associative: true);

        return is_array($data) ? $data : [];
    }

    /**
     * Translate a key with fallback chain.
     */
    public function translate(string $key, string $locale, string $namespace = 'ui', array $replace = []): string
    {
        $translations = $this->load($locale, $namespace);

        $value = $translations[$key] ?? $key;

        if ($replace !== []) {
            $value = strtr($value, $replace);
        }

        return $value;
    }

    /**
     * Get the localized route prefix ('' for 'en', '/vi' for 'vi').
     */
    public function localePrefix(string $locale): string
    {
        if ($locale === 'en') {
            return '';
        }

        return '/'.$locale;
    }
}
