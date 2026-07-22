<?php

declare(strict_types=1);

namespace App\Services\Seo;

class SitemapGenerator
{
    /** @var list<string> */
    private const AVAILABLE_LOCALES = ['en', 'vi'];

    /** @var list<array{path: string, priority: float, changefreq: string}> */
    private const PAGES = [
        ['path' => '', 'priority' => 1.0, 'changefreq' => 'weekly'],
        ['path' => '/component-gallery', 'priority' => 0.5, 'changefreq' => 'monthly'],
        ['path' => '/payment', 'priority' => 0.7, 'changefreq' => 'monthly'],
    ];

    public function generate(): string
    {
        $urls = [];

        foreach (self::PAGES as $page) {
            foreach (self::AVAILABLE_LOCALES as $locale) {
                $loc = $locale === 'en' ? '' : '/' . $locale;
                $href = 'https://geo119.com' . $loc . $page['path'] ?: '/';

                $alternates = [];
                foreach (self::AVAILABLE_LOCALES as $alt) {
                    $altLoc = $alt === 'en' ? '' : '/' . $alt;
                    $alternates[] = $alt;
                }

                $urls[] = $this->urlElement($href, $page['priority'], $page['changefreq'], $alternates);
            }
        }

        return $this->document(implode("\n", $urls));
    }

    /**
     * @param list<string> $alternates
     */
    private function urlElement(string $loc, float $priority, string $changefreq, array $alternates): string
    {
        $xml = "  <url>\n";
        $xml .= '    <loc>' . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . "</loc>\n";

        foreach ($alternates as $alt) {
            $altLoc = $alt === 'en' ? '' : '/' . $alt;
            $altUrl = str_replace('https://geo119.com', 'https://geo119.com' . $altLoc, $loc);
            $xml .= '    <xhtml:link rel="alternate" hreflang="' . $alt . '" href="'
                . htmlspecialchars($altUrl, ENT_XML1, 'UTF-8') . "\" />\n";
        }

        $xml .= '    <priority>' . number_format($priority, 1) . "</priority>\n";
        $xml .= '    <changefreq>' . $changefreq . "</changefreq>\n";
        $xml .= "  </url>";

        return $xml;
    }

    private function document(string $urls): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
            . ' xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n"
            . $urls . "\n"
            . '</urlset>';
    }
}
