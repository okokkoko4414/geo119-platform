<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\I18n\LocaleDetector;

class SetLocale
{
    public function __construct(
        private readonly LocaleDetector $detector,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $locale = $this->detector->detect($request);

        app()->setLocale($locale);
        setlocale(LC_TIME, $locale . '_' . strtoupper($locale) . '.UTF-8');

        return $next($request);
    }
}
