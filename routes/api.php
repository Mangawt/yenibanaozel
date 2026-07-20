<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Api\ExtensionAuthController;
use App\Http\Controllers\Api\ExtensionMeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['api.public_limit', 'api.log'])->group(function (): void {
    Route::post('/auth/login', [ExtensionAuthController::class, 'login'])
        ->middleware('extension.limit:login')
        ->name('api.auth.login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [ExtensionAuthController::class, 'logout'])->name('api.auth.logout');
        Route::get('/me', [ExtensionMeController::class, 'show'])
            ->middleware(['extension.ability:extension:read', 'extension.limit:read'])
            ->name('api.me');
        Route::get('/me/list', [ExtensionMeController::class, 'list'])
            ->middleware(['extension.ability:extension:read', 'extension.limit:read'])
            ->name('api.me.list');
        Route::post('/me/list', [ExtensionMeController::class, 'store'])
            ->middleware(['extension.ability:extension:list-write', 'extension.limit:write'])
            ->name('api.me.list.store');
        Route::delete('/me/list/{media}/{status}', [ExtensionMeController::class, 'destroyStatus'])
            ->middleware(['extension.ability:extension:list-write', 'extension.limit:delete'])
            ->whereIn('status', \App\Support\MediaListStatus::all())
            ->name('api.me.list.destroy-status');
        Route::delete('/me/list/{media}', [ExtensionMeController::class, 'destroy'])
            ->middleware(['extension.ability:extension:list-write', 'extension.limit:delete'])
            ->name('api.me.list.destroy');
        Route::get('/media/{media}/my-list', [ExtensionMeController::class, 'mediaStatus'])
            ->middleware(['extension.ability:extension:read', 'extension.limit:read'])
            ->name('api.media.my-list');
    });

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
