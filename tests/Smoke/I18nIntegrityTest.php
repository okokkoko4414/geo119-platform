<?php

declare(strict_types=1);

use function Pest\Laravel\get;

test('zero Chinese characters in English homepage', function (): void {
    $response = get('/');
    expect($response->content())->not->toMatch('/\p{Han}/u');
});

test('zero Chinese characters in English component gallery', function (): void {
    $response = get('/en/component-gallery');
    expect($response->content())->not->toMatch('/\p{Han}/u');
});

test('zero Chinese characters in English payment page', function (): void {
    $response = get('/en/payment');
    expect($response->content())->not->toMatch('/\p{Han}/u');
});

test('zero Chinese characters in Vietnamese homepage', function (): void {
    $response = get('/vi/');
    expect($response->content())->not->toMatch('/\p{Han}/u');
});

test('all UI translation keys render without errors', function (): void {
    $response = get('/en/component-gallery');
    $response->assertStatus(200);
    // Key names would appear in output if translation is missing
    // (Laravel returns the key name as fallback)
    expect($response->content())->not->toContain('ui.');
});
