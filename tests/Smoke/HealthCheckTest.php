<?php

declare(strict_types=1);

use function Pest\Laravel\get;

test('health endpoint returns 200', function (): void {
    $response = get('/health');
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'status',
        'timestamp',
        'checks' => [
            'database' => ['healthy'],
            'redis' => ['healthy'],
            'cache' => ['healthy'],
        ],
    ]);
});

test('homepage returns 200 in English', function (): void {
    $response = get('/en/');
    $response->assertStatus(200);
});

test('homepage returns 200 in Vietnamese', function (): void {
    $response = get('/vi/');
    $response->assertStatus(200);
});

test('homepage falls back to English with no locale prefix', function (): void {
    $response = get('/');
    $response->assertStatus(200);
});
