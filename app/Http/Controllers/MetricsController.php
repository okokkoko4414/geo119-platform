<?php

namespace App\Http\Controllers;

use App\Services\ClaudeLocal\ClaudeLocalClient;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Redis;

class MetricsController extends Controller
{
    public function __invoke(ClaudeLocalClient $claudeLocal): Response
    {
        $metrics = [];

        // Queue metrics
        $queueSizes = Redis::command('llen', ['queues:default']) ?: 0;
        $metrics[] = '# HELP laravel_queue_depth Current queue depth';
        $metrics[] = '# TYPE laravel_queue_depth gauge';
        $metrics[] = "laravel_queue_depth{queue=\"default\"} {$queueSizes}";

        // Circuit breaker state
        $cb = $claudeLocal->getCircuitBreaker();
        $metrics[] = '# HELP claude_local_circuit_breaker_state Circuit breaker state (0=closed, 1=half-open, 2=open)';
        $metrics[] = '# TYPE claude_local_circuit_breaker_state gauge';
        $state = match ($cb->getState()) {
            'closed' => 0,
            'half-open' => 1,
            'open' => 2,
        };
        $metrics[] = "claude_local_circuit_breaker_state {$state}";

        // Rate limiter tokens
        $rl = $claudeLocal->getRateLimiter();
        $tokens = $rl->getAvailableTokens();
        $metrics[] = '# HELP claude_local_rate_limiter_tokens Available tokens';
        $metrics[] = '# TYPE claude_local_rate_limiter_tokens gauge';
        $metrics[] = "claude_local_rate_limiter_tokens {$tokens}";

        // Cost tracking
        $cost = $claudeLocal->getCostTracker()->getSummary();
        $metrics[] = '# HELP claude_local_requests_total Total requests';
        $metrics[] = '# TYPE claude_local_requests_total counter';
        $metrics[] = "claude_local_requests_total {$cost['total_requests']}";
        $metrics[] = '# HELP claude_local_cost_cents_total Total cost in cents';
        $metrics[] = '# TYPE claude_local_cost_cents_total counter';
        $metrics[] = "claude_local_cost_cents_total {$cost['total_cost_cents']}";

        return response(implode("\n", $metrics)."\n", 200)
            ->header('Content-Type', 'text/plain; version=0.0.4');
    }
}
