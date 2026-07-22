<?php

declare(strict_types=1);

use App\Models\User;
use function Pest\Laravel\get;

dataset('routes', [
    '/',
    '/en/',
    '/vi/',
]);

test('analytics dashboard renders in English', function (): void {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->get('/en/dashboard/analytics');
    $response->assertStatus(200);
});

test('analytics dashboard renders in Vietnamese', function (): void {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->get('/vi/dashboard/analytics');
    $response->assertStatus(200);
});

test('sitemap.xml returns 200', function (): void {
    $response = get('/sitemap.xml');
    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/xml');
});

test('language switch POST endpoint returns redirect', function (): void {
    $response = $this->withoutMiddleware()->post('/language/switch', [
        'locale' => 'vi',
        'redirect_to' => '/',
    ]);
    $response->assertStatus(302);
});
