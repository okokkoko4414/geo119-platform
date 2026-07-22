<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\ClaudeLocal\ClaudeLocalClient;
use App\Services\Contracts\RedisStore;
use App\Services\Redis\PhpRedisStore;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RedisStore::class, PhpRedisStore::class);

        $this->app->singleton(
            ClaudeLocalClient::class,
            static fn ($app) => new ClaudeLocalClient(
                endpoint: config('services.deepseek.endpoint'),
                apiKey: config('services.deepseek.api_key'),
            ),
        );
    }

    public function boot(): void
    {
        //
    }
}
