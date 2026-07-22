<?php

declare(strict_types=1);

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ComponentGalleryController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\OptimizationResultsController;
use App\Http\Controllers\OptimizeController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\ResultsController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/health', HealthController::class)->name('health');

// Fallback routes for {locale?} prefix when locale is absent.
Route::middleware('web')->group(function (): void {
    // Auth
    Route::get('/signup', [RegisterController::class, 'show'])->name('signup.fallback');
    Route::post('/signup', [RegisterController::class, 'store'])->middleware('throttle:3,1')->name('signup.store.fallback');
    Route::get('/login', [LoginController::class, 'show'])->name('login.fallback');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1')->name('login.store.fallback');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout.fallback');

    // Protected
    Route::middleware('auth')->group(function (): void {
        Route::get('/dashboard', [AnalyticsController::class, 'index'])->name('dashboard.fallback');
        Route::get('/dashboard/analytics', [AnalyticsController::class, 'index'])->name('analytics.dashboard.fallback');
        Route::get('/dashboard/optimizations/{id}', [OptimizationResultsController::class, 'showFallback'])->name('optimizations.show.fallback');
        Route::get('/optimize', [OptimizeController::class, 'index'])->name('optimize.fallback');
    });

    // Public
    Route::get('/results', [ResultsController::class, 'index'])->name('results.fallback');
    Route::get('/component-gallery', [ComponentGalleryController::class, 'index'])->name('component-gallery.fallback');
    Route::get('/payment', [PaymentController::class, 'show'])->name('payment.show.fallback');
    Route::post('/payment/process', [PaymentController::class, 'process'])->name('payment.process.fallback');
});

// Static routes that must not be consumed by {locale?} prefix
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::post('/language/switch', [LanguageController::class, 'switch'])->name('language.switch');

// Optimization detail page — outside {locale?} prefix to avoid parameter binding issues
Route::get('/{locale?}/dashboard/optimizations/{id}', [OptimizationResultsController::class, 'show'])
    ->where('locale', '[a-z]{2}')
    ->middleware('web')
    ->name('optimizations.show');

Route::prefix('{locale?}')->middleware('web')->group(function (): void {
    Route::get('/', [HomeController::class, 'index'])->name('locale.home');

    // Auth
    Route::get('/signup', [RegisterController::class, 'show'])->name('signup');
    Route::post('/signup', [RegisterController::class, 'store'])->middleware('throttle:3,1')->name('signup.store');
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Protected
    Route::middleware('auth')->group(function (): void {
        Route::get('/dashboard', [AnalyticsController::class, 'index'])->name('dashboard');
        Route::get('/dashboard/analytics', [AnalyticsController::class, 'index'])->name('analytics.dashboard');
        Route::get('/optimize', [OptimizeController::class, 'index'])->name('optimize');
    });

    // Public
    Route::get('/results', [ResultsController::class, 'index'])->name('results');
    Route::get('/component-gallery', [ComponentGalleryController::class, 'index'])
        ->name('component-gallery');
    Route::get('/payment', [PaymentController::class, 'show'])->name('payment.show');
    Route::post('/payment/process', [PaymentController::class, 'process'])->name('payment.process');
});
