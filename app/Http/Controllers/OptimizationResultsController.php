<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\OptimizationResult;
use Illuminate\View\View;

final class OptimizationResultsController extends Controller
{
    /**
     * GET /{locale?}/dashboard/optimizations/{id}
     *
     * Shows before/after scores and full detail for a single optimization.
     */
    public function show(?string $locale, string $id): View
    {
        $result = OptimizationResult::findOrFail($id);

        return view('pages.optimizations.show', [
            'locale' => $locale ?? app()->getLocale(),
            'result' => $result,
        ]);
    }

    /**
     * GET /dashboard/optimizations/{id} (no locale prefix)
     *
     * Delegates to show() with null locale.
     */
    public function showFallback(string $id): View
    {
        return $this->show(null, $id);
    }
}
