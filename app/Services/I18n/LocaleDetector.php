<?php

declare(strict_types=1);

namespace App\Services\I18n;

use Illuminate\Http\Request;

class LocaleDetector
{
    private const COOKIE_NAME = 'geo119_locale';

    private const COOKIE_TTL = 60 * 24 * 365; // 1 year

    /**
     * @return list<string>
     */
    public function availableLocales(): array
    {
        return collect(config('languages.languages', []))
            ->pluck('code')
            ->values()
            ->all() ?: ['en'];
    }

    /**
     * Detection pipeline: URL segment -> Cookie -> Accept-Language -> 'en' fallback.
     */
    public function detect(Request $request): string
    {
        return $this->fromUrl($request)
            ?? $this->fromCookie($request)
            ?? $this->fromHeader($request)
            ?? 'en';
    }

    private function fromUrl(Request $request): ?string
    {
        $segment = $request->segment(1);

        if ($segment !== null && in_array($segment, $this->availableLocales(), strict: true)) {
            return $segment;
        }

        return null;
    }

    private function fromCookie(Request $request): ?string
    {
        $cookie = $request->cookie(self::COOKIE_NAME);

        if (is_string($cookie) && in_array($cookie, $this->availableLocales(), strict: true)) {
            return $cookie;
        }

        return null;
    }

    private function fromHeader(Request $request): ?string
    {
        $acceptLanguage = $request->header('Accept-Language');

        if ($acceptLanguage === null) {
            return null;
        }

        /** @var string $acceptLanguage */
        foreach ($this->parseAcceptLanguage($acceptLanguage) as $locale) {
            if (in_array($locale, $this->availableLocales(), strict: true)) {
                return $locale;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function parseAcceptLanguage(string $header): array
    {
        $locales = [];
        $parts = explode(',', $header);

        foreach ($parts as $part) {
            $subParts = explode(';', trim($part));
            $locale = strtolower(trim($subParts[0]));
            // Strip quality values, keep primary language tag
            if (str_contains($locale, '-')) {
                $locale = explode('-', $locale)[0];
            }
            $locales[] = $locale;
        }

        return $locales;
    }

    public function setCookie(string $locale): void
    {
        if (! in_array($locale, $this->availableLocales(), strict: true)) {
            return;
        }

        setcookie(
            name: self::COOKIE_NAME,
            value: $locale,
            expires_or_options: time() + self::COOKIE_TTL,
            path: '/',
            domain: '',
            secure: true,
            httponly: true,
            same_site: 'Lax',
        );
    }
}
