<?php

declare(strict_types=1);

use App\Services\I18n\LocaleDetector;

test('detect falls back to english with no hints', function (): void {
    $request = Request::create('/');
    $detector = new LocaleDetector;
    $locale = $detector->detect($request);

    expect($locale)->toBe('en');
});

test('detect reads locale from URL segment', function (): void {
    $request = Request::create('/vi/');
    $detector = new LocaleDetector;
    $locale = $detector->detect($request);

    expect($locale)->toBe('vi');
});

test('availableLocales returns non-empty array', function (): void {
    $detector = new LocaleDetector;
    $locales = $detector->availableLocales();

    expect($locales)->toBeArray();
    expect($locales)->not->toBeEmpty();
    expect(in_array('en', $locales, true))->toBeTrue();
});
