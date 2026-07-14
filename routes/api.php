<?php

use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/search', [ApiController::class, 'search'])->name('api.search');
    Route::post('/bulk-import', [ApiController::class, 'bulkImport'])->name('api.bulk-import');
    Route::get('/anime/{media:slug}', [ApiController::class, 'show'])
        ->defaults('type', 'anime')
        ->name('api.anime.show');
    Route::get('/manga/{media:slug}', [ApiController::class, 'show'])
        ->defaults('type', 'manga')
        ->name('api.manga.show');
});
