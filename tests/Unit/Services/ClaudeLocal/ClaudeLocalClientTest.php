<?php

declare(strict_types=1);

namespace Tests\Unit\Services\ClaudeLocal;

use App\Services\ClaudeLocal\ClaudeLocalClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.deepseek.endpoint' => 'http://test-endpoint:8080']);
    config(['services.deepseek.api_key' => 'test-key']);

    $this->client = new ClaudeLocalClient;
});

afterEach(function () {
    $redis = Redis::connection('cache');
    $redis->del(
        'circuit_breaker:default:failures',
        'circuit_breaker:default:opened_at',
        'rate_limiter:default:tokens',
        'rate_limiter:default:last_refill',
        'cost_tracker:default:requests',
        'cost_tracker:default:input_tokens',
        'cost_tracker:default:output_tokens',
        'cost_tracker:default:cost_cents',
        'cost_tracker:default:latency_ms',
        'cost_tracker:default:recent',
    );
});

test('chat sends request and parses response', function () {
    Http::fake([
        'test-endpoint:8080/v1/chat/completions' => Http::response([
            'id' => 'chat-1',
            'model' => 'deepseek-chat',
            'choices' => [
                ['message' => ['content' => 'Hello!'], 'finish_reason' => 'stop'],
            ],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20],
        ], 200),
    ]);

    $result = $this->client->chat([['role' => 'user', 'content' => 'Hi']]);

    expect($result['content'])->toBe('Hello!')
        ->and($result['model'])->toBe('deepseek-chat')
        ->and($result['input_tokens'])->toBe(10)
        ->and($result['output_tokens'])->toBe(20)
        ->and($result['latency_ms'])->toBeGreaterThanOrEqual(0);
});

test('chat fails when circuit breaker is open', function () {
    $cb = $this->client->getCircuitBreaker();
    $cb->recordFailure();
    $cb->recordFailure();
    $cb->recordFailure();
    $cb->recordFailure();
    $cb->recordFailure();

    expect($cb->isOpen())->toBeTrue();
    expect(fn () => $this->client->chat([['role' => 'user', 'content' => 'Hi']]))
        ->toThrow(\RuntimeException::class, 'circuit breaker is open');
});

test('chat fails when rate limited', function () {
    // Set Redis token count to 0 directly to simulate exhaustion
    Redis::connection('cache')->set('rate_limiter:default:tokens', 0.0);

    expect(fn () => $this->client->chat([['role' => 'user', 'content' => 'Hi']]))
        ->toThrow(\RuntimeException::class, 'rate limit exceeded');
});

test('chat throws on API error', function () {
    Http::fake([
        'test-endpoint:8080/v1/chat/completions' => Http::response('Forbidden', 403),
    ]);

    expect(fn () => $this->client->chat([['role' => 'user', 'content' => 'Hi']]))
        ->toThrow(\RuntimeException::class, '403');
});

test('chat throws on connection error', function () {
    Http::fake([
        'test-endpoint:8080/v1/chat/completions' => function () {
            throw new ConnectionException('Connection refused');
        },
    ]);

    expect(fn () => $this->client->chat([['role' => 'user', 'content' => 'Hi']]))
        ->toThrow(\RuntimeException::class, 'Connection refused');
});

test('translate calls chat with system prompt', function () {
    Http::fake(function ($request) {
        $body = json_decode($request->body(), true);

        expect($body['messages'])
            ->toHaveCount(2)
            ->and($body['messages'][0]['role'])->toBe('system')
            ->and($body['messages'][1]['content'])->toContain('fr');

        return Http::response([
            'choices' => [['message' => ['content' => 'Bonjour!']]],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 3],
            'model' => 'deepseek-chat',
        ]);
    });

    $result = $this->client->translate('Hello!', 'fr');

    expect($result['content'])->toBe('Bonjour!');
});

test('translate includes optional context', function () {
    Http::fake(function ($request) {
        $body = json_decode($request->body(), true);

        expect($body['messages'][1]['content'])->toContain('formal email');

        return Http::response([
            'choices' => [['message' => ['content' => 'Bonjour!']]],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 3],
            'model' => 'deepseek-chat',
        ]);
    });

    $result = $this->client->translate('Hello!', 'fr', 'formal email');

    expect($result['content'])->toBe('Bonjour!');
});

test('optimize calls chat and parses JSON result', function () {
    Http::fake([
        'test-endpoint:8080/v1/chat/completions' => Http::response([
            'choices' => [['message' => [
                'content' => json_encode([
                    'before_score' => 0.5,
                    'after_score' => 0.8,
                    'optimized_content' => 'Optimized!',
                    'changes_summary' => 'Improved clarity',
                ]),
            ]]],
            'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 30],
            'model' => 'deepseek-chat',
        ], 200),
    ]);

    $result = $this->client->optimize('Some content', 'improve clarity');

    expect($result['optimized']['before_score'])->toBe(0.5)
        ->and($result['optimized']['after_score'])->toBe(0.8)
        ->and($result['optimized']['optimized_content'])->toBe('Optimized!')
        ->and($result['optimized']['changes_summary'])->toBe('Improved clarity');
});

test('successful response resets circuit breaker', function () {
    $this->client->getCircuitBreaker()->recordFailure();

    Http::fake([
        'test-endpoint:8080/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'OK']]],
            'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
            'model' => 'deepseek-chat',
        ], 200),
    ]);

    $this->client->chat([['role' => 'user', 'content' => 'test']]);

    expect($this->client->getCircuitBreaker()->getFailureCount())->toBe(0);
});

test('API failure is recorded in circuit breaker', function () {
    Http::fake([
        'test-endpoint:8080/v1/chat/completions' => Http::response('Error', 500),
    ]);

    expect(fn () => $this->client->chat([['role' => 'user', 'content' => 'Hi']]))
        ->toThrow(\RuntimeException::class);

    expect($this->client->getCircuitBreaker()->getFailureCount())->toBe(1);
});

test('connection error is recorded in circuit breaker', function () {
    Http::fake([
        'test-endpoint:8080/v1/chat/completions' => function () {
            throw new ConnectionException('timeout');
        },
    ]);

    expect(fn () => $this->client->chat([['role' => 'user', 'content' => 'Hi']]))
        ->toThrow(\RuntimeException::class);

    expect($this->client->getCircuitBreaker()->getFailureCount())->toBe(1);
});
