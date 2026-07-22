<?php

declare(strict_types=1);

use function Pest\Laravel\get;

test('component gallery renders in English', function (): void {
    $response = get('/en/component-gallery');
    $response->assertStatus(200);
});

test('component gallery renders in Vietnamese', function (): void {
    $response = get('/vi/component-gallery');
    $response->assertStatus(200);
});

test('payment page renders in English', function (): void {
    $response = get('/en/payment');
    $response->assertStatus(200);
});

test('payment page renders in Vietnamese', function (): void {
    $response = get('/vi/payment');
    $response->assertStatus(200);
});
