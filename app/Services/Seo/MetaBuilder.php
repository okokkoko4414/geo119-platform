<?php

declare(strict_types=1);

namespace App\Services\Seo;

class MetaBuilder
{
    /**
     * @param array{title?: string, description?: string, og_image?: string, og_type?: string, canonical?: string} $params
     * @return string
     */
    public function render(array $params, string $locale = 'en'): string
    {
        $title = $params['title'] ?? 'GEO119 — Language Quality Optimization';
        $description = $params['description']
            ?? 'Optimize translation quality across 70+ languages. Real-time scoring, batch optimization, and effect tracking.';
        $ogImage = $params['og_image'] ?? 'https://geo119.com/build/images/og-default.png';
        $ogType = $params['og_type'] ?? 'website';
        $canonical = $params['canonical'] ?? url()->current();

        $tags = [];
        $tags[] = '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
        $tags[] = '<meta name="description" content="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '">';
        $tags[] = '<meta property="og:title" content="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">';
        $tags[] = '<meta property="og:description" content="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '">';
        $tags[] = '<meta property="og:image" content="' . htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8') . '">';
        $tags[] = '<meta property="og:type" content="' . htmlspecialchars($ogType, ENT_QUOTES, 'UTF-8') . '">';
        $tags[] = '<meta property="og:locale" content="' . htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') . '">';
        $tags[] = '<link rel="canonical" href="' . htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') . '">';
        $tags[] = '<meta charset="UTF-8">';
        $tags[] = '<meta name="viewport" content="width=device-width, initial-scale=1.0">';

        return implode("\n    ", $tags);
    }
}
