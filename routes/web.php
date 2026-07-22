<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ComponentGalleryController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\LanguageController;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/health', App\Http\Controllers\HealthController::class)->name('health');

Route::prefix('{locale?}')->middleware('web')->group(function (): void {
    Route::get('/', [HomeController::class, 'index'])->name('locale.home');
    Route::get('/component-gallery', [ComponentGalleryController::class, 'index'])
        ->name('component-gallery');
    Route::get('/payment', [PaymentController::class, 'show'])->name('payment.show');
    Route::post('/payment/process', [PaymentController::class, 'process'])->name('payment.process');
    Route::post('/language/switch', [LanguageController::class, 'switch'])->name('language.switch');
    Route::get('/sitemap.xml', [App\Http\Controllers\SeoController::class, 'sitemap'])->name('sitemap');
    Route::get('/dashboard/analytics', [AnalyticsController::class, 'index'])->name('analytics.dashboard');
});
