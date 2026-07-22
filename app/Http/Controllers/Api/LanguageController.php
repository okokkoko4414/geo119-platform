<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Language;
use Illuminate\Http\JsonResponse;

class LanguageController extends Controller
{
    public function index(): JsonResponse
    {
        $languages = Language::orderBy("tier")->orderBy("code")->get()
            ->map(fn (Language $lang): array => [
                "locale" => $lang->code,
                "name" => $lang->name,
                "tier" => $lang->tier,
                "quality_score" => $lang->quality_score,
                "baseline_score" => $lang->baseline_score,
            ]);

        return response()->json($languages);
    }
}
