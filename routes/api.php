<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\AnalyticsController;

Route::middleware('api')->prefix('v1')->group(function (): void {
    // B1 — Translation
    Route::get('/locale/{locale}/translations', [
        App\Http\Controllers\Api\TranslationController::class, 'show',
    ])->name('api.translations');

    Route::get('/locale/{locale}/cache-status', [
        App\Http\Controllers\Api\TranslationController::class, 'cacheStatus',
    ])->name('api.translations.cache');

    // B4 — Payment
    Route::post('/payment/intent', [
        App\Http\Controllers\Api\PaymentController::class, 'createIntent',
    ])->name('api.payment.intent');

    Route::post('/payment/confirm', [
        App\Http\Controllers\Api\PaymentController::class, 'confirm',
    ])->name('api.payment.confirm');

    Route::get('/payment/cost', [
        App\Http\Controllers\Api\PaymentController::class, 'estimateCost',
    ])->name('api.payment.cost');

    // B2 — Analytics
    Route::get('/analytics/time-series', [AnalyticsController::class, 'timeSeries'])
        ->name('api.analytics.time-series');
    Route::get('/analytics/language-breakdown', [AnalyticsController::class, 'languageBreakdown'])
        ->name('api.analytics.language-breakdown');

    // B3 — Batch optimization
    Route::post('/batch/optimize', [
        App\Http\Controllers\BatchController::class, 'optimize',
    ])->name('api.batch.optimize');
});

// B2 — Event tracking (no prefix for minimal latency)
Route::post('/e/track', [EventController::class, 'track'])
    ->middleware('throttle:120,1')
    ->name('api.events.track');
Route::get('/e/live', [EventController::class, 'live'])->name('api.events.live');
