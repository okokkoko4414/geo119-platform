<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('accepts valid impression event', function (): void {
    $response = $this->postJson('/api/e/track', [
        'type' => 'impression',
        'target' => 'https://geo119.com/en/',
        'locale' => 'en',
    ]);

    $response->assertNoContent();
});

it('accepts valid click event', function (): void {
    $response = $this->postJson('/api/e/track', [
        'type' => 'click',
        'target' => 'https://geo119.com/en/pricing',
        'locale' => 'en',
    ]);

    $response->assertNoContent();
});

it('rejects missing type field', function (): void {
    $response = $this->postJson('/api/e/track', [
        'target' => 'https://geo119.com/en/',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

it('rejects invalid event type', function (): void {
    $response = $this->postJson('/api/e/track', [
        'type' => 'invalid_event_type',
        'target' => 'https://geo119.com/en/',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

it('accepts event with optional metadata', function (): void {
    $response = $this->postJson('/api/e/track', [
        'type' => 'impression',
        'target' => 'https://geo119.com/en/',
        'metadata' => ['page_type' => 'home', 'section' => 'hero'],
    ]);

    $response->assertNoContent();
});

it('accepts event with user_id and session_id', function (): void {
    $response = $this->postJson('/api/e/track', [
        'type' => 'click',
        'target' => 'https://geo119.com/en/pricing',
        'user_id' => '550e8400-e29b-41d4-a716-446655440000',
        'session_id' => 'abc123def456',
    ]);

    $response->assertNoContent();
});

it('rejects excessively long type', function (): void {
    $response = $this->postJson('/api/e/track', [
        'type' => str_repeat('a', 51),
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

it('SSE endpoint returns event stream headers', function (): void {
    $response = $this->get('/api/e/live');

    $response->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');
    $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
});
