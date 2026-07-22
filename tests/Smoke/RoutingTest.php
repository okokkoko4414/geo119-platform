<?php

declare(strict_types=1);

use function Pest\Laravel\get;

dataset('routes', [
    '/',
    '/en/',
    '/vi/',
]);

test('analytics dashboard renders in English', function (): void {
    $response = get('/en/dashboard/analytics');
    $response->assertStatus(200);
});

test('analytics dashboard renders in Vietnamese', function (): void {
    $response = get('/vi/dashboard/analytics');
    $response->assertStatus(200);
});

test('sitemap.xml returns 200', function (): void {
    $response = get('/sitemap.xml');
    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
});

test('language switch POST endpoint returns redirect', function (): void {
    $response = $this->post('/language/switch', [
        '_token' => csrf_token(),
        'locale' => 'vi',
        'redirect_to' => '/',
    ]);
    $response->assertStatus(302);
});
