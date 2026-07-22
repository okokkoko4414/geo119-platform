<?php

declare(strict_types=1);

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\LanguageController;
use App\Http\Controllers\Api\OptimizationResultsController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\TranslationController;
use App\Http\Controllers\BatchController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::get("/health", function (): JsonResponse {
    return response()->json([
        "status" => "ok",
        "service" => "geoflow",
        "version" => config("app.version", "0.1.0"),
        "timestamp" => now()->toIso8601String(),
    ]);
})->name("health");

Route::get("/api/health", function (): JsonResponse {
    return response()->json([
        "status" => "ok",
        "service" => "geoflow",
        "version" => config("app.version", "0.1.0"),
        "timestamp" => now()->toIso8601String(),
    ]);
})->name("api.health");

Route::middleware("api")->group(function (): void {
    // B2 — Event tracking (no version prefix for minimal latency)
    Route::post("/e/track", [EventController::class, "track"])
        ->middleware("throttle:120,1")
        ->name("api.events.track");
    Route::get("/e/live", [EventController::class, "live"])
        ->name("api.events.live");

    // B2 — Analytics (direct paths without v1 prefix for Phase B acceptance)
    Route::get("/analytics/impressions", [AnalyticsController::class, "impressions"])
        ->name("api.analytics.impressions.direct");
    Route::get("/analytics/dashboard", [AnalyticsController::class, "dashboard"])
        ->name("api.analytics.dashboard.direct");

    Route::prefix("v1")->group(function (): void {
        // B1 — Languages
        Route::get("/languages", [LanguageController::class, "index"])
            ->name("api.languages");

        // B1 — Translation
        Route::get("/locale/{locale}/translations", [TranslationController::class, "show"])
            ->name("api.translations");
        Route::get("/locale/{locale}/cache-status", [TranslationController::class, "cacheStatus"])
            ->name("api.translations.cache");

        // B4 — Payment
        Route::post("/payment/intent", [PaymentController::class, "createIntent"])
            ->name("api.payment.intent");
        Route::post("/payment/confirm", [PaymentController::class, "confirm"])
            ->name("api.payment.confirm");
        Route::get("/payment/cost", [PaymentController::class, "estimateCost"])
            ->name("api.payment.cost");

        // B2 — Analytics
        Route::get("/analytics/impressions", [AnalyticsController::class, "impressions"])
            ->name("api.analytics.impressions");
        Route::get("/analytics/clicks", [AnalyticsController::class, "clicks"])
            ->name("api.analytics.clicks");
        Route::get("/analytics/ctr", [AnalyticsController::class, "ctr"])
            ->name("api.analytics.ctr");
        Route::get("/analytics/time-series", [AnalyticsController::class, "timeSeries"])
            ->name("api.analytics.time-series");
        Route::get("/analytics/language-breakdown", [AnalyticsController::class, "languageBreakdown"])
            ->name("api.analytics.language-breakdown");

        // B2 — Optimization results
        Route::get("/optimizations/recent", [OptimizationResultsController::class, "recent"])
            ->name("api.optimizations.recent");
        Route::get("/optimizations/{id}", [OptimizationResultsController::class, "show"])
            ->name("api.optimizations.show");

        // B3 — Batch optimization
        Route::post("/batch/optimize", [BatchController::class, "submit"])
            ->name("api.batch.optimize");
        Route::get("/batch/{jobId}", [BatchController::class, "status"])
            ->name("api.batch.status");
    });
});
