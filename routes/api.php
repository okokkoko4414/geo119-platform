<?php

declare(strict_types=1);

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\TranslationController;
use App\Http\Controllers\BatchController;
use Illuminate\Support\Facades\Route;

Route::middleware('api')->prefix('v1')->group(function (): void {
    // B1 — Translation
    Route::get('/locale/{locale}/translations', [
        TranslationController::class, 'show',
    ])->name('api.translations');

    Route::get('/locale/{locale}/cache-status', [
        TranslationController::class, 'cacheStatus',
    ])->name('api.translations.cache');

    // B4 — Payment
    Route::post('/payment/intent', [
        PaymentController::class, 'createIntent',
    ])->name('api.payment.intent');

    Route::post('/payment/confirm', [
        PaymentController::class, 'confirm',
    ])->name('api.payment.confirm');

    Route::get('/payment/cost', [
        PaymentController::class, 'estimateCost',
    ])->name('api.payment.cost');

    // B2 — Analytics
    Route::get('/analytics/time-series', [AnalyticsController::class, 'timeSeries'])
        ->name('api.analytics.time-series');
    Route::get('/analytics/language-breakdown', [AnalyticsController::class, 'languageBreakdown'])
        ->name('api.analytics.language-breakdown');

    // B3 — Batch optimization
    Route::post('/batch/optimize', [
        BatchController::class, 'optimize',
    ])->name('api.batch.optimize');
});

// B2 — Event tracking (no prefix for minimal latency)
Route::post('/e/track', [EventController::class, 'track'])
    ->middleware('throttle:120,1')
    ->name('api.events.track');
Route::get('/e/live', [EventController::class, 'live'])->name('api.events.live');
