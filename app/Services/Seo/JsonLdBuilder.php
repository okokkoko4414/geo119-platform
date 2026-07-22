<?php

declare(strict_types=1);

namespace App\Services\Seo;

class JsonLdBuilder
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function organization(array $data = []): string
    {
        $json = array_merge([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'GEO119',
            'url' => 'https://geo119.com',
            'description' => 'Language quality optimization platform — 70-language translation quality scoring and optimization',
        ], $data);

        return $this->render($json);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function breadcrumb(array $items): string
    {
        $listItems = [];
        foreach ($items as $i => $item) {
            $listItems[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ];
        }

        return $this->render([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $listItems,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function article(array $data): string
    {
        $json = array_merge([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
        ], $data);

        return $this->render($json);
    }

    private function render(mixed $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return '<script type="application/ld+json">'."\n".$json."\n".'</script>';
    }
}
