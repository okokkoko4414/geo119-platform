<?php

declare(strict_types=1);

use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

test('translation API returns JSON for English', function (): void {
    $response = get('/api/v1/locale/en/translations');
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'locale',
        'translations',
    ]);
});

test('translation API returns JSON for Vietnamese', function (): void {
    $response = get('/api/v1/locale/vi/translations');
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'locale',
        'translations',
    ]);
});

test('payment cost API returns estimate', function (): void {
    $response = get('/api/v1/payment/cost?character_count=1000&language_pair=en-vi&service_level=standard');
    $response->assertStatus(200);
});

test('batch optimize endpoint requires auth or proper payload', function (): void {
    // Without proper auth/payload, should return 401 or 422, not 500
    $response = postJson('/api/v1/batch/optimize', []);
    $response->assertStatus(422);
});

test('event tracking endpoint rejects empty payload', function (): void {
    $response = postJson('/e/track', []);
    $response->assertStatus(422);
});
