<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Services\I18n\LocaleDetector;

class LanguageController extends Controller
{
    public function switch(Request $request, LocaleDetector $detector): RedirectResponse
    {
        $locale = $request->input('locale', 'en');
        $detector->setCookie($locale);

        $prefix = $locale === 'en' ? '' : '/' . $locale;
        $redirectTo = $request->input('redirect_to', '/');

        return redirect($prefix . $redirectTo);
    }
}
