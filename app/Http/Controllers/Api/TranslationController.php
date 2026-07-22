<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\I18n\TranslationLoader;
use Illuminate\Http\JsonResponse;

class TranslationController extends Controller
{
    public function show(string $locale, TranslationLoader $loader): JsonResponse
    {
        $namespaces = ['ui', 'errors', 'emails', 'payment'];

        $translations = [];
        foreach ($namespaces as $ns) {
            $translations[$ns] = $loader->load($locale, $ns);
        }

        return response()->json([
            'locale' => $locale,
            'translations' => $translations,
        ]);
    }
}
