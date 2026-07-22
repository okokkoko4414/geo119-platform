<?php

declare(strict_types=1);

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ComponentGalleryController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/health', HealthController::class)->name('health');

// Static routes that must not be consumed by {locale?} prefix
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::post('/language/switch', [LanguageController::class, 'switch'])->name('language.switch');

Route::prefix('{locale?}')->middleware('web')->group(function (): void {
    Route::get('/', [HomeController::class, 'index'])->name('locale.home');
    Route::get('/component-gallery', [ComponentGalleryController::class, 'index'])
        ->name('component-gallery');
    Route::get('/payment', [PaymentController::class, 'show'])->name('payment.show');
    Route::post('/payment/process', [PaymentController::class, 'process'])->name('payment.process');
    Route::get('/dashboard/analytics', [AnalyticsController::class, 'index'])->name('analytics.dashboard');
});
