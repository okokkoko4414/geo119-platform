<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\I18n\LocaleDetector;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        return view('pages.home', [
            'locale' => app()->getLocale(),
            'availableLocales' => app(LocaleDetector::class)->availableLocales(),
        ]);
    }
}
