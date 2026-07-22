<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Seo\JsonLdBuilder;
use App\Services\Seo\SitemapGenerator;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(SitemapGenerator $generator): Response
    {
        $xml = $generator->generate();

        return response($xml, 200)
            ->header('Content-Type', 'application/xml');
    }
}
