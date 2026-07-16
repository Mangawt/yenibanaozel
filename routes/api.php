<?php

use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['api.public_limit', 'api.log'])->group(function (): void {
    Route::get('/search', [ApiController::class, 'search'])->name('api.search');
    Route::get('/discover', [ApiController::class, 'discover'])->name('api.discover');
    Route::get('/trending', [ApiController::class, 'trending'])->name('api.trending');
    Route::get('/popular', [ApiController::class, 'popular'])->name('api.popular');
    Route::get('/latest', [ApiController::class, 'latest'])->name('api.latest');
    Route::get('/random', [ApiController::class, 'random'])->name('api.random');
    Route::get('/autocomplete', [ApiController::class, 'autocomplete'])->name('api.autocomplete');
    Route::get('/media', [ApiController::class, 'media'])->name('api.media');
    Route::post('/media/batch', [ApiController::class, 'mediaBatch'])->name('api.media.batch');
    Route::get('/recommendations/{slug}', [ApiController::class, 'recommendations'])->name('api.recommendations');
    Route::get('/studios', [ApiController::class, 'studios'])->name('api.studios');
    Route::get('/studios/{slug}', [ApiController::class, 'studio'])->name('api.studios.show');
    Route::get('/people', [ApiController::class, 'people'])->name('api.people');
    Route::get('/people/{slug}', [ApiController::class, 'person'])->name('api.people.show');
    Route::get('/profiles', [ApiController::class, 'profiles'])->name('api.profiles');
    Route::get('/profiles/{username}', [ApiController::class, 'profile'])->name('api.profiles.show');
    Route::get('/profiles/{username}/followers', [ApiController::class, 'profileFollowers'])->name('api.profiles.followers');
    Route::get('/profiles/{username}/following', [ApiController::class, 'profileFollowing'])->name('api.profiles.following');
    Route::get('/openapi.json', [ApiController::class, 'openapi'])->name('api.openapi');
    Route::get('/anime/{media:slug}', [ApiController::class, 'show'])
        ->defaults('type', 'anime')
        ->name('api.anime.show');
    Route::get('/anime/{slug}/similar', [ApiController::class, 'recommendations'])->name('api.anime.similar');
    Route::get('/manga/{media:slug}', [ApiController::class, 'show'])
        ->defaults('type', 'manga')
        ->name('api.manga.show');
    Route::get('/manga/{slug}/similar', [ApiController::class, 'recommendations'])->name('api.manga.similar');
});
