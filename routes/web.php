<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\StudioController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/ara', [HomeController::class, 'search'])->name('search');
Route::get('/api', [ApiController::class, 'docs'])->name('api.docs');
Route::get('/hakkimizda', [PageController::class, 'about'])->name('about');
Route::get('/gizlilik-politikasi', [PageController::class, 'privacy'])->name('privacy');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('seo.robots');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/autocomplete', [HomeController::class, 'autocomplete'])->name('autocomplete');
Route::get('/sanatcilar', [PersonController::class, 'index'])->name('people.index');
Route::get('/sanatci/{slug}', [PersonController::class, 'show'])->name('people.show');
Route::get('/studyolar', [StudioController::class, 'index'])->name('studios.index');
Route::get('/studyo/{slug}', [StudioController::class, 'show'])->name('studios.show');
Route::get('/{type}/{media:slug}', [HomeController::class, 'show'])
    ->whereIn('type', ['anime', 'manga'])
    ->name('media.show');

Route::prefix('adminasip')->name('admin.')->group(function (): void {
    Route::get('/', [AdminController::class, 'login'])->name('login');
    Route::post('/login', [AdminController::class, 'authenticate'])->name('authenticate');
    Route::post('/logout', [AdminController::class, 'logout'])->name('logout');

    Route::middleware('adminasip')->group(function (): void {
        Route::get('/panel', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::post('/import', [AdminController::class, 'import'])->name('import');
        Route::post('/bulk-import', [AdminController::class, 'bulkImport'])->name('bulk-import');
        Route::get('/import-queue', [AdminController::class, 'queue'])->name('import-queue');
        Route::post('/import-queue/preview', [AdminController::class, 'previewQueue'])->name('import-queue.preview');
        Route::post('/import-queue/enqueue', [AdminController::class, 'enqueueQueue'])->name('import-queue.enqueue');
        Route::post('/import-queue/process', [AdminController::class, 'processQueue'])->name('import-queue.process');
        Route::post('/import-queue/{queueItem}/retry', [AdminController::class, 'retryQueue'])->name('import-queue.retry');
        Route::get('/ayarlar', [AdminController::class, 'settings'])->name('settings');
        Route::post('/ayarlar', [AdminController::class, 'saveSettings'])->name('settings.save');
    });
});
