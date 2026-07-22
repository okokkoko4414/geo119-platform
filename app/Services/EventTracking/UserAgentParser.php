<?php

declare(strict_types=1);

namespace App\Services\EventTracking;

final class UserAgentParser
{
    private const MOBILE_PATTERNS = [
        '/Mobile/i', '/Android/i', '/iPhone/i', '/iPad/i', '/iPod/i',
        '/Windows Phone/i', '/BlackBerry/i', '/Opera Mini/i',
    ];

    private const TABLET_PATTERNS = [
        '/iPad/i', '/Tablet/i', '/Android(?!.*Mobile)/i',
    ];

    private const BOT_PATTERNS = [
        '/bot/i', '/crawler/i', '/spider/i', '/scraper/i',
        '/headless/i', '/selenium/i', '/puppeteer/i', '/playwright/i',
    ];

    /**
     * @return array{device_type: string, browser: string, is_bot: bool}
     */
    public function parse(string $userAgent): array
    {
        return [
            'device_type' => $this->detectDeviceType($userAgent),
            'browser' => $this->detectBrowser($userAgent),
            'is_bot' => $this->isBot($userAgent),
        ];
    }

    private function detectDeviceType(string $ua): string
    {
        foreach (self::TABLET_PATTERNS as $pattern) {
            if (preg_match($pattern, $ua)) {
                return 'tablet';
            }
        }

        foreach (self::MOBILE_PATTERNS as $pattern) {
            if (preg_match($pattern, $ua)) {
                return 'mobile';
            }
        }

        return 'desktop';
    }

    private function detectBrowser(string $ua): string
    {
        if (str_contains($ua, 'Edg/')) {
            return 'Edge';
        }
        if (str_contains($ua, 'Chrome/') && ! str_contains($ua, 'Edg/')) {
            return 'Chrome';
        }
        if (str_contains($ua, 'Safari/') && ! str_contains($ua, 'Chrome/')) {
            return 'Safari';
        }
        if (str_contains($ua, 'Firefox/')) {
            return 'Firefox';
        }
        if (str_contains($ua, 'OPR/') || str_contains($ua, 'Opera/')) {
            return 'Opera';
        }

        return 'Other';
    }

    public function isBot(string $ua): bool
    {
        if ($ua === '' || $ua === '-') {
            return true;
        }

        foreach (self::BOT_PATTERNS as $pattern) {
            if (preg_match($pattern, $ua)) {
                return true;
            }
        }

        return false;
    }
}
