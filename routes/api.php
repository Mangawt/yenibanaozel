<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\ExtensionAuthController;
use App\Http\Controllers\Api\ExtensionMeController;
use App\Http\Controllers\Api\GoogleMobileAuthController;
use App\Http\Controllers\Api\ProfileApiController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware([
        'api.log',
    ])
    ->group(function (): void {
        Route::post(
            '/auth/register',
            [ExtensionAuthController::class, 'register'],
        )
            ->middleware([
                'extension.limit:register',
            ])
            ->name('api.auth.register');

        Route::post(
            '/auth/google',
            [GoogleMobileAuthController::class, 'login'],
        )
            ->middleware([
                'extension.limit:google-auth',
            ])
            ->name('api.auth.google');

        Route::post(
            '/auth/login',
            [ExtensionAuthController::class, 'login'],
        )
            ->middleware('extension.limit:login')
            ->name('api.auth.login');

        Route::middleware('auth:sanctum')
            ->group(function (): void {
                Route::post(
                    '/auth/logout',
                    [ExtensionAuthController::class, 'logout'],
                )
                    ->name('api.auth.logout');


                Route::delete(
                    '/me/account',
                    [AccountController::class, 'destroy'],
                )
                    ->middleware([
                        'extension.limit:account-sensitive',
                    ])
                    ->name('api.me.account.destroy');


                Route::delete(
                    '/me/account/google',
                    [AccountController::class, 'destroyWithGoogle'],
                )
                    ->middleware([
                        'extension.limit:account-sensitive',
                    ])
                    ->name('api.me.account.google.destroy');

                Route::get(
                    '/me',
                    [ExtensionMeController::class, 'show'],
                )
                    ->middleware([
                        'extension.ability:extension:read',
                        'extension.limit:read',
                    ])
                    ->name('api.me');

                Route::get(
                    '/me/list',
                    [ExtensionMeController::class, 'list'],
                )
                    ->middleware([
                        'extension.ability:extension:read',
                        'extension.limit:read',
                    ])
                    ->name('api.me.list');

                Route::post(
                    '/me/list',
                    [ExtensionMeController::class, 'store'],
                )
                    ->middleware([
                        'extension.ability:extension:list-write',
                        'extension.limit:list-write',
                    ])
                    ->name('api.me.list.store');

                Route::delete(
                    '/me/list/{media}/{status}',
                    [ExtensionMeController::class, 'destroyStatus'],
                )
                    ->middleware([
                        'extension.ability:extension:list-write',
                        'extension.limit:list-write',
                    ])
                    ->whereIn(
                        'status',
                        \App\Support\MediaListStatus::all(),
                    )
                    ->name('api.me.list.destroy-status');

                Route::delete(
                    '/me/list/{media}',
                    [ExtensionMeController::class, 'destroy'],
                )
                    ->middleware([
                        'extension.ability:extension:list-write',
                        'extension.limit:list-write',
                    ])
                    ->name('api.me.list.destroy');

                Route::get(
                    '/media/{media:slug}/my-list',
                    [ExtensionMeController::class, 'mediaStatus'],
                )
                    ->middleware([
                        'extension.ability:extension:read',
                        'extension.limit:read',
                    ])
                    ->name('api.media.my-list');

                Route::post(
                    '/media/{media:slug}/comments',
                    [CommentController::class, 'store'],
                )
                    ->middleware([
                        'extension.ability:app:comment-write',
                        'extension.limit:comment-write',
                    ])
                    ->name('api.media.comments.store');

                Route::patch(
                    '/comments/{comment}',
                    [CommentController::class, 'update'],
                )
                    ->middleware(
                        'throttle:account-sensitive',
                    )
                    ->name('api.comments.update');

                Route::delete(
                    '/comments/{comment}',
                    [CommentController::class, 'destroy'],
                )
                    ->middleware(
                        'throttle:account-sensitive',
                    )
                    ->name('api.comments.destroy');

                Route::post(
                    '/comments/{comment}/vote',
                    [CommentController::class, 'vote'],
                )
                    ->middleware([
                        'extension.ability:app:vote-write',
                        'extension.limit:comment-vote',
                    ])
                    ->name('api.comments.vote');

                Route::post(
                    '/comments/{comment}/report',
                    [CommentController::class, 'report'],
                )
                    ->middleware([
                        'extension.ability:app:report-write',
                        'extension.limit:report-write',
                    ])
                    ->name('api.comments.report');


                Route::patch(
                    '/me/profile',
                    [ProfileApiController::class, 'update'],
                )
                    ->middleware([
                        'extension.ability:app:social-write',
                        'extension.limit:profile-write',
                    ])
                    ->name('api.me.profile.update');

                Route::post(
                    '/me/avatar',
                    [ProfileApiController::class, 'uploadAvatar'],
                )
                    ->middleware([
                        'extension.ability:app:social-write',
                        'extension.limit:profile-write',
                    ])
                    ->name('api.me.avatar.store');

                Route::delete(
                    '/me/avatar',
                    [ProfileApiController::class, 'deleteAvatar'],
                )
                    ->middleware([
                        'extension.ability:app:social-write',
                        'extension.limit:profile-write',
                    ])
                    ->name('api.me.avatar.destroy');

                Route::post(
                    '/me/banner',
                    [ProfileApiController::class, 'uploadBanner'],
                )
                    ->middleware([
                        'extension.ability:app:social-write',
                        'extension.limit:profile-write',
                    ])
                    ->name('api.me.banner.store');

                Route::delete(
                    '/me/banner',
                    [ProfileApiController::class, 'deleteBanner'],
                )
                    ->middleware([
                        'extension.ability:app:social-write',
                        'extension.limit:profile-write',
                    ])
                    ->name('api.me.banner.destroy');

                Route::post(
                    '/users/{username}/follow',
                    [ProfileApiController::class, 'follow'],
                )
                    ->middleware([
                        'extension.ability:app:social-write',
                        'extension.limit:follow-write',
                    ])
                    ->name('api.users.follow');

                Route::delete(
                    '/users/{username}/follow',
                    [ProfileApiController::class, 'unfollow'],
                )
                    ->middleware([
                        'extension.ability:app:social-write',
                        'extension.limit:follow-write',
                    ])
                    ->name('api.users.unfollow');


                Route::post(
                    '/users/{username}/report',
                    [ProfileApiController::class, 'report'],
                )
                    ->middleware([
                        'extension.ability:app:report-write',
                        'extension.limit:report-write',
                    ])
                    ->name('api.users.report');

                Route::get(
                    '/me/notifications',
                    [NotificationController::class, 'index'],
                )
                    ->middleware([
                        'extension.ability:app:read',
                        'extension.limit:notification-read',
                    ])
                    ->name('api.me.notifications');

                Route::get(
                    '/me/notifications/unread-count',
                    [NotificationController::class, 'unreadCount'],
                )
                    ->middleware([
                        'extension.ability:app:read',
                        'extension.limit:notification-read',
                    ])
                    ->name('api.me.notifications.unread-count');

                Route::post(
                    '/me/notifications/read-all',
                    [NotificationController::class, 'readAll'],
                )
                    ->middleware([
                        'extension.ability:app:read',
                        'extension.limit:notification-read',
                    ])
                    ->name('api.me.notifications.read-all');

                Route::post(
                    '/me/notifications/{notification}/read',
                    [NotificationController::class, 'read'],
                )
                    ->middleware([
                        'extension.ability:app:read',
                        'extension.limit:notification-read',
                    ])
                    ->name('api.me.notifications.read');
            });

        Route::middleware('api.public_limit')
            ->group(function (): void {
                Route::get(
                    '/search',
            [ApiController::class, 'search'],
        )->name('api.search');

        Route::get(
            '/discover',
            [ApiController::class, 'discover'],
        )->name('api.discover');

        Route::get(
            '/trending',
            [ApiController::class, 'trending'],
        )->name('api.trending');

        Route::get(
            '/popular',
            [ApiController::class, 'popular'],
        )->name('api.popular');

        Route::get(
            '/season-popular',
            [ApiController::class, 'seasonPopular'],
        )->name('api.season-popular');

        Route::get(
            '/latest',
            [ApiController::class, 'latest'],
        )->name('api.latest');

        Route::get(
            '/random',
            [ApiController::class, 'random'],
        )->name('api.random');

        Route::get(
            '/autocomplete',
            [ApiController::class, 'autocomplete'],
        )->name('api.autocomplete');

        Route::get(
            '/media',
            [ApiController::class, 'media'],
        )->name('api.media');

        Route::get(
            '/media/{media:slug}/comments',
            [CommentController::class, 'index'],
        )
            ->middleware('throttle:60,1')
            ->name('api.media.comments');

        Route::post(
            '/media/batch',
            [ApiController::class, 'mediaBatch'],
        )->name('api.media.batch');

        Route::get(
            '/recommendations/{slug}',
            [ApiController::class, 'recommendations'],
        )->name('api.recommendations');

        Route::get(
            '/studios',
            [ApiController::class, 'studios'],
        )->name('api.studios');

        Route::get(
            '/studios/{slug}',
            [ApiController::class, 'studio'],
        )->name('api.studios.show');

        Route::get(
            '/people',
            [ApiController::class, 'people'],
        )->name('api.people');

        Route::get(
            '/people/{slug}',
            [ApiController::class, 'person'],
        )->name('api.people.show');

        Route::get(
            '/tags/{slug}',
            [ApiController::class, 'tag'],
        )->name('api.tags.show');

        Route::get(
            '/characters/{slug}',
            [ApiController::class, 'character'],
        )->name('api.characters.show');


        Route::get(
            '/users/{username}/followers',
            [ProfileApiController::class, 'followers'],
        )->name('api.users.followers');

        Route::get(
            '/users/{username}/following',
            [ProfileApiController::class, 'following'],
        )->name('api.users.following');

        Route::get(
            '/users/{username}/comments',
            [ProfileApiController::class, 'comments'],
        )->name('api.users.comments');

        Route::get(
            '/users/{username}/favorites',
            [ProfileApiController::class, 'favorites'],
        )->name('api.users.favorites');

        Route::get(
            '/users/{username}/anime-list',
            [ProfileApiController::class, 'animeList'],
        )->name('api.users.anime-list');

        Route::get(
            '/users/{username}/manga-list',
            [ProfileApiController::class, 'mangaList'],
        )->name('api.users.manga-list');

        Route::get(
            '/users/{username}',
            [ProfileApiController::class, 'show'],
        )->name('api.users.show');

        Route::get(
            '/profiles',
            [ApiController::class, 'profiles'],
        )->name('api.profiles');

        Route::get(
            '/profiles/{username}/followers',
            [ApiController::class, 'profileFollowers'],
        )->name('api.profiles.followers');

        Route::get(
            '/profiles/{username}/following',
            [ApiController::class, 'profileFollowing'],
        )->name('api.profiles.following');

        Route::get(
            '/profiles/{username}',
            [ApiController::class, 'profile'],
        )->name('api.profiles.show');

        Route::get(
            '/openapi.json',
            [ApiController::class, 'openapi'],
        )->name('api.openapi');

        Route::get(
            '/anime/{media:slug}',
            [ApiController::class, 'show'],
        )
            ->defaults('type', 'anime')
            ->name('api.anime.show');

        Route::get(
            '/anime/{slug}/similar',
            [ApiController::class, 'recommendations'],
        )->name('api.anime.similar');

        Route::get(
            '/manga/{media:slug}',
            [ApiController::class, 'show'],
        )
            ->defaults('type', 'manga')
            ->name('api.manga.show');

        Route::get(
            '/manga/{slug}/similar',
            [ApiController::class, 'recommendations'],
        )->name('api.manga.similar');
            });
    });
