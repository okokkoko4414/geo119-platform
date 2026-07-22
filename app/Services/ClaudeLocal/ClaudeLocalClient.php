<?php

namespace App\Services\ClaudeLocal;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Psr\Log\LoggerInterface;

class ClaudeLocalClient
{
    private string $endpoint;

    private string $apiKey;

    private CircuitBreaker $circuitBreaker;

    private RateLimiter $rateLimiter;

    private CostTracker $costTracker;

    private LoggerInterface $logger;

    public function __construct(
        ?string $endpoint = null,
        ?string $apiKey = null,
        ?CircuitBreaker $circuitBreaker = null,
        ?RateLimiter $rateLimiter = null,
        ?CostTracker $costTracker = null,
    ) {
        $this->endpoint = $endpoint ?? config('services.deepseek.endpoint', 'http://localhost:8080');
        $this->apiKey = $apiKey ?? config('services.deepseek.api_key', '');
        $this->circuitBreaker = $circuitBreaker ?? new CircuitBreaker;
        $this->rateLimiter = $rateLimiter ?? new RateLimiter;
        $this->costTracker = $costTracker ?? new CostTracker;
        $this->logger = app('log');
    }

    public function chat(array $messages, array $options = []): array
    {
        if ($this->circuitBreaker->isOpen()) {
            throw new \RuntimeException('ClaudeLocal circuit breaker is open');
        }

        if (! $this->rateLimiter->tryAcquire()) {
            throw new \RuntimeException('ClaudeLocal rate limit exceeded');
        }

        $startTime = microtime(true);

        try {
            $response = Http::timeout($options['timeout'] ?? 120)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->endpoint.'/v1/chat/completions', [
                    'model' => $options['model'] ?? 'deepseek-chat',
                    'messages' => $messages,
                    'temperature' => $options['temperature'] ?? 0.7,
                    'max_tokens' => $options['max_tokens'] ?? 4096,
                ]);

            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            if (! $response->successful()) {
                $this->circuitBreaker->recordFailure();
                $this->logger->error('ClaudeLocal API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \RuntimeException("ClaudeLocal API returned {$response->status()}");
            }

            $data = $response->json();
            $usage = $data['usage'] ?? [];

            $this->circuitBreaker->recordSuccess();
            $this->costTracker->record(
                $options['model'] ?? 'deepseek-chat',
                $usage['prompt_tokens'] ?? 0,
                $usage['completion_tokens'] ?? 0,
                $latencyMs,
                $options['locale'] ?? null,
            );

            return [
                'content' => $data['choices'][0]['message']['content'] ?? '',
                'model' => $data['model'] ?? 'unknown',
                'input_tokens' => $usage['prompt_tokens'] ?? 0,
                'output_tokens' => $usage['completion_tokens'] ?? 0,
                'latency_ms' => $latencyMs,
            ];
        } catch (ConnectionException $e) {
            $this->circuitBreaker->recordFailure();
            $this->logger->error('ClaudeLocal connection error', ['error' => $e->getMessage()]);

            throw new \RuntimeException('ClaudeLocal unreachable: '.$e->getMessage(), 0, $e);
        }
    }

    public function translate(string $source, string $targetLocale, ?string $context = null): array
    {
        $systemPrompt = 'You are a professional translator. Translate the following text to the target language. Maintain tone, style, and formatting. Respond with only the translated text, no explanations.';

        $userMessage = "Target language code: {$targetLocale}\n\n";
        if ($context) {
            $userMessage .= "Context (surrounding text for coherence): {$context}\n\n";
        }
        $userMessage .= "Text to translate:\n{$source}";

        return $this->chat([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userMessage],
        ], [
            'temperature' => 0.3,
            'locale' => $targetLocale,
        ]);
    }

    public function optimize(string $content, string $objective, array $parameters = []): array
    {
        $systemPrompt = 'You are an AI content optimizer. Improve the given content based on the specified objective. Return JSON with "before_score" (0-1), "after_score" (0-1), "optimized_content", and "changes_summary" fields.';

        $userMessage = "Objective: {$objective}\n";
        if ($parameters) {
            $userMessage .= 'Parameters: '.json_encode($parameters)."\n\n";
        }
        $userMessage .= "Content:\n{$content}";

        $result = $this->chat([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userMessage],
        ], [
            'temperature' => 0.5,
            'max_tokens' => 8192,
        ]);

        $parsed = json_decode($result['content'], true);

        return array_merge($result, [
            'optimized' => $parsed,
        ]);
    }

    public function getCircuitBreaker(): CircuitBreaker
    {
        return $this->circuitBreaker;
    }

    public function getRateLimiter(): RateLimiter
    {
        return $this->rateLimiter;
    }

    public function getCostTracker(): CostTracker
    {
        return $this->costTracker;
    }
}
