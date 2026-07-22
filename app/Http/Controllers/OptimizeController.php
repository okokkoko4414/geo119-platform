<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Language;
use Illuminate\View\View;

final class OptimizeController extends Controller
{
    public function index(): View
    {
        $languages = Language::active()->orderBy('tier')->orderBy('code')->get()
            ->map(fn (Language $lang): array => [
                'code' => $lang->code,
                'name' => $lang->name,
                'tier' => $lang->tier,
            ]);

        return view('pages.optimize', [
            'locale' => app()->getLocale(),
            'languages' => $languages,
        ]);
    }
}
