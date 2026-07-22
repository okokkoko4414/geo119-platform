<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\OptimizationResult;
use Illuminate\View\View;

final class ResultsController extends Controller
{
    public function index(): View
    {
        $results = OptimizationResult::query()
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('pages.results', [
            'locale' => app()->getLocale(),
            'results' => $results,
        ]);
    }
}
