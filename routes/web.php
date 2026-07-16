<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminContentController;
use App\Http\Controllers\AdminSyncController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\SocialController;
use App\Http\Controllers\StudioController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/ara', [HomeController::class, 'search'])->name('search');
Route::get('/api', [ApiController::class, 'docs'])->name('api.docs');
Route::get('/giris', [AuthController::class, 'login'])->name('login');
Route::post('/giris', [AuthController::class, 'authenticate'])->name('login.authenticate');
Route::get('/kayit', [AuthController::class, 'register'])->name('register');
Route::post('/kayit', [AuthController::class, 'store'])->name('register.store');
Route::post('/cikis', [AuthController::class, 'logout'])->name('logout');
Route::get('/profil', [ProfileController::class, 'edit'])->middleware('auth')->name('profile.edit');
Route::post('/profil', [ProfileController::class, 'update'])->middleware('auth')->name('profile.update');
Route::get('/listem', [ProfileController::class, 'list'])->middleware('auth')->name('profile.list');
Route::get('/u/{username}/liste', [ProfileController::class, 'publicList'])->name('profile.public-list');
Route::get('/u/{username}', [ProfileController::class, 'show'])->name('profile.show');
Route::get('/u/{username}/takipciler', [ProfileController::class, 'followers'])->name('profile.followers');
Route::get('/u/{username}/takip', [ProfileController::class, 'following'])->name('profile.following');
Route::get('/hakkimizda', [PageController::class, 'about'])->name('about');
Route::get('/gizlilik-politikasi', [PageController::class, 'privacy'])->name('privacy');
Route::get('/kullanim-sartlari', [PageController::class, 'terms'])->name('terms');
Route::get('/cerez-politikasi', [PageController::class, 'cookies'])->name('cookies');
Route::get('/telif-ve-icerik-kaldirma', [PageController::class, 'copyright'])->name('copyright');
Route::get('/sorumluluk-reddi', [PageController::class, 'disclaimer'])->name('disclaimer');
Route::get('/iletisim', [PageController::class, 'contact'])->name('contact');
Route::get('/cerez-tercihleri', [PageController::class, 'cookiePreferences'])->name('cookie-preferences');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('seo.robots');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/autocomplete', [HomeController::class, 'autocomplete'])->name('autocomplete');
Route::get('/sanatcilar', [PersonController::class, 'index'])->name('people.index');
Route::get('/sanatci/{slug}', [PersonController::class, 'show'])->name('people.show');
Route::get('/studyolar', [StudioController::class, 'index'])->name('studios.index');
Route::get('/studyo/{slug}', [StudioController::class, 'show'])->name('studios.show');
Route::middleware('auth')->group(function (): void {
    Route::post('/u/{user}/follow', [SocialController::class, 'follow'])->name('profile.follow');
    Route::post('/u/{user}/report', [SocialController::class, 'reportUser'])->name('profile.report');
    Route::post('/media/{media}/liste', [SocialController::class, 'updateMediaList'])->name('media.list');
    Route::delete('/media/{media}/liste', [SocialController::class, 'removeMediaList'])->name('media.list.remove');
    Route::post('/media/{media}/favori', [SocialController::class, 'toggleFavorite'])->name('media.favorite');
    Route::post('/media/{media}/yorum', [SocialController::class, 'comment'])->name('media.comment');
    Route::post('/yorum/{comment}/oy', [SocialController::class, 'voteComment'])->name('comments.vote');
    Route::post('/yorum/{comment}/sikayet', [SocialController::class, 'reportComment'])->name('comments.report');
});
Route::get('/{type}/{media:slug}', [HomeController::class, 'show'])
    ->whereIn('type', ['anime', 'manga'])
    ->name('media.show');

Route::prefix('adminasip')->name('admin.')->group(function (): void {
    Route::get('/', [AdminController::class, 'login'])->name('login');
    Route::get('/login', [AdminController::class, 'login'])->name('login.form');
    Route::post('/login', [AdminController::class, 'authenticate'])->name('authenticate');
});

Route::prefix('admin')->name('admin.')->middleware('admin.user')->group(function (): void {
    Route::get('/', fn () => redirect()->route('admin.dashboard'))->name('home');
    Route::post('/logout', [AdminController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/anime', [AdminContentController::class, 'anime'])->name('anime.index');
    Route::get('/manga', [AdminContentController::class, 'manga'])->name('manga.index');
    Route::get('/media/{media}/edit', [AdminContentController::class, 'edit'])->name('media.edit');
    Route::put('/media/{media}', [AdminContentController::class, 'update'])->middleware('admin.write')->name('media.update');
    Route::delete('/media/bulk-destroy', [AdminContentController::class, 'bulkDestroy'])->middleware('admin.write')->name('media.bulk-destroy');
    Route::delete('/media/{media}', [AdminContentController::class, 'destroy'])->middleware('admin.write')->name('media.destroy');
    Route::get('/people', [AdminContentController::class, 'people'])->name('people.index');
    Route::get('/characters', [AdminContentController::class, 'characters'])->name('characters.index');
    Route::get('/studios', [AdminContentController::class, 'studios'])->name('studios.index');
    Route::post('/import', [AdminController::class, 'import'])->middleware('admin.write')->name('import');
    Route::post('/bulk-import', [AdminController::class, 'bulkImport'])->middleware('admin.write')->name('bulk-import');
    Route::get('/imports', [AdminController::class, 'queue'])->name('import-queue');
    Route::get('/imports/stats', [AdminController::class, 'queueStats'])->name('import-queue.stats');
    Route::post('/imports/preview', [AdminController::class, 'previewQueue'])->middleware('admin.write')->name('import-queue.preview');
    Route::post('/imports/enqueue', [AdminController::class, 'enqueueQueue'])->middleware('admin.write')->name('import-queue.enqueue');
    Route::post('/imports/actions', [AdminController::class, 'queueAction'])->middleware('admin.write')->name('import-queue.action');
    Route::post('/imports/{queueItem}/retry', [AdminController::class, 'retryQueue'])->middleware('admin.write')->name('import-queue.retry');
    Route::get('/sync', [AdminSyncController::class, 'index'])->name('sync.index');
    Route::post('/sync/start', [AdminSyncController::class, 'start'])->middleware('admin.write')->name('sync.start');
    Route::post('/sync/{syncState}/pause', [AdminSyncController::class, 'pause'])->middleware('admin.write')->name('sync.pause');
    Route::post('/sync/{syncState}/resume', [AdminSyncController::class, 'resume'])->middleware('admin.write')->name('sync.resume');
    Route::post('/sync/{syncState}/stop', [AdminSyncController::class, 'stop'])->middleware('admin.write')->name('sync.stop');
    Route::delete('/sync/{syncState}', [AdminSyncController::class, 'destroy'])->middleware('admin.write')->name('sync.destroy');
    Route::get('/status', [AdminController::class, 'status'])->name('status');
    Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
    Route::post('/settings', [AdminController::class, 'saveSettings'])->middleware('admin.write')->name('settings.save');
    Route::post('/settings/test-translation', [AdminController::class, 'testTranslation'])->middleware('admin.write')->name('settings.translation.test');
    Route::get('/users', [AdminController::class, 'users'])->name('users.index');
    Route::put('/users/{user}', [AdminController::class, 'updateUser'])->middleware('admin.write')->name('users.update');
    Route::get('/reports', [AdminController::class, 'reports'])->name('reports.index');
    Route::put('/reports/{report}', [AdminController::class, 'updateReport'])->middleware('admin.write')->name('reports.update');
    Route::delete('/comments/{comment}', [AdminController::class, 'destroyComment'])->middleware('admin.write')->name('comments.destroy');
});
