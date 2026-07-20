<?php

namespace App\Http\Controllers;

use App\Models\ImportQueue;
use App\Models\Media;
use App\Models\Comment;
use App\Models\Report;
use App\Models\User;
use App\Services\ExternalMediaService;
use App\Services\ImportQueueService;
use App\Services\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function login()
    {
        if (Auth::check() && $this->isAdmin(Auth::user()?->role)) {
            return redirect()->route('admin.dashboard');
        }

        if (Auth::check()) {
            abort(404);
        }

        return view('admin.login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = Str::lower($validated['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Çok fazla deneme yapıldı. Lütfen biraz sonra tekrar dene.'])->onlyInput('email');
        }

        if (! Auth::attempt($validated, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            Log::channel('security')->warning('Başarısız admin girişi.', [
                'email' => $validated['email'],
                'ip' => $request->ip(),
            ]);

            return back()->withErrors(['email' => 'E-posta veya parola hatalı.'])->onlyInput('email');
        }

        if (! $this->isAdmin($request->user()?->role)) {
            Auth::logout();
            RateLimiter::hit($key, 60);

            return back()->withErrors(['email' => 'Bu kullanıcı admin paneline erişemez.'])->onlyInput('email');
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    public function dashboard(Request $request, ExternalMediaService $external, Settings $settings)
    {
        $results = [];

        if ($request->filled('q')) {
            $validated = $request->validate([
                'type' => ['required', 'in:anime,manga'],
                'q' => ['required', 'string', 'max:120'],
            ]);

            $results = $external->search('anilist', $validated['type'], $validated['q']);
        }

        return view('admin.dashboard', [
            'settings' => $settings->allPublic(),
            'results' => $results,
            'mediaCount' => Media::query()->count(),
            'animeCount' => Media::query()->where('type', 'anime')->count(),
            'mangaCount' => Media::query()->where('type', 'manga')->count(),
            'queueCount' => ImportQueue::query()->count(),
            'failedCount' => ImportQueue::query()->where('status', ImportQueue::STATUS_FAILED)->count(),
        ]);
    }

    public function import(Request $request, ExternalMediaService $external): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:anime,manga'],
            'id' => ['required', 'integer'],
        ]);

        $media = $external->import('anilist', $validated['type'], (int) $validated['id']);

        return redirect()
            ->route('media.show', ['type' => $media->type, 'media' => $media])
            ->with('status', "{$media->title} içe aktarıldı.");
    }

    public function bulkImport(Request $request, ExternalMediaService $external): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:anime,manga'],
            'q' => ['nullable', 'string', 'max:120'],
            'sort' => ['required', 'in:POPULARITY_DESC,TRENDING_DESC,SCORE_DESC,START_DATE_DESC'],
            'per_page' => ['required', 'integer', 'min:1', 'max:25'],
            'page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'genre' => ['nullable', 'string', 'max:80'],
            'year' => ['nullable', 'integer', 'min:1940', 'max:2100'],
            'season' => ['nullable', 'in:WINTER,SPRING,SUMMER,FALL'],
            'format' => ['nullable', 'string', 'max:40'],
        ]);

        $result = $external->importBatch($validated);

        return back()->with('status', "{$result['count']} içerik doğrudan aktarıldı.");
    }

    public function queue(Request $request, ImportQueueService $queue, Settings $settings)
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:pending,running,completed,failed,skipped'],
            'type' => ['nullable', 'in:anime,manga'],
            'source' => ['nullable', 'string', 'max:32'],
            'external_id' => ['nullable', 'integer'],
            'batch_id' => ['nullable', 'string', 'max:80'],
            'error' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', 'in:newest,oldest,status,attempts,updated'],
        ]);

        $items = ImportQueue::query()
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['source'] ?? null, fn ($query, $source) => $query->where('source', $source))
            ->when($filters['external_id'] ?? null, fn ($query, $id) => $query->where('external_id', $id))
            ->when($filters['batch_id'] ?? null, fn ($query, $batchId) => $query->where('batch_id', 'like', '%'.$batchId.'%'))
            ->when($filters['error'] ?? null, fn ($query, $error) => $query->where('error_message', 'like', '%'.$error.'%'));

        match ($filters['sort'] ?? 'newest') {
            'oldest' => $items->oldest(),
            'status' => $items->orderBy('status')->latest('updated_at'),
            'attempts' => $items->orderByDesc('attempts')->latest('updated_at'),
            'updated' => $items->latest('updated_at'),
            default => $items->latest(),
        };

        return view('admin.import-queue', [
            'settings' => $settings->allPublic(),
            'stats' => $queue->stats(),
            'preview' => $request->session()->get('import_queue_preview'),
            'items' => $items->paginate(30)->withQueryString(),
        ]);
    }

    public function queueStats(ImportQueueService $queue)
    {
        return response()->json($queue->stats());
    }

    public function status(Settings $settings, ImportQueueService $queue)
    {
        return view('admin.status', [
            'settings' => $settings->allPublic(),
            'queueStats' => $queue->stats(),
            'jobs' => [
                'waiting' => $this->tableCount('jobs'),
                'failed' => $this->tableCount('failed_jobs'),
                'batches' => $this->tableCount('job_batches'),
            ],
            'latestFailedJobs' => Schema::hasTable('failed_jobs')
                ? DB::table('failed_jobs')->latest('failed_at')->limit(10)->get()
                : collect(),
            'logs' => $this->recentLogs(),
        ]);
    }

    public function previewQueue(Request $request, ImportQueueService $queue): RedirectResponse
    {
        $validated = $this->queueValidation($request);
        $preview = $queue->preview($validated + ['source' => 'anilist']);

        $request->session()->put('import_queue_preview', $preview);

        return redirect()
            ->route('admin.import-queue')
            ->with('status', 'Keşif tamamlandı. Kuyruğa eklemeden önce özeti kontrol edebilirsin.');
    }

    public function enqueueQueue(Request $request, ImportQueueService $queue): RedirectResponse
    {
        $preview = $request->session()->pull('import_queue_preview');

        if (! $preview) {
            return back()->withErrors(['queue' => 'Önce keşif önizlemesi oluşturmalısın.']);
        }

        $result = $queue->enqueue($preview['options'], $preview['entries'] ?? $preview['ids']);

        return redirect()
            ->route('admin.import-queue')
            ->with('status', "{$result['created']} kayıt kuyruğa eklendi, {$result['completed_existing']} kayıt zaten sitede olduğu için tamamlandı, {$result['skipped']} kayıt atlandı. Batch: ".($result['batch_id'] ?? '-'));
    }

    public function retryQueue(ImportQueue $queueItem, ImportQueueService $queue): RedirectResponse
    {
        abort_unless($queueItem->status === ImportQueue::STATUS_FAILED, 404);

        $queue->retry($queueItem);

        return back()->with('status', 'Kayıt tekrar kuyruğa alındı.');
    }

    public function queueAction(Request $request, ImportQueueService $queue): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:retry_failed,clear_completed,clear_failed,clear_skipped,clear_pending'],
        ]);

        $count = match ($validated['action']) {
            'retry_failed' => $queue->retryFailed(),
            'clear_completed' => $queue->clearStatus(ImportQueue::STATUS_COMPLETED),
            'clear_failed' => $queue->clearStatus(ImportQueue::STATUS_FAILED),
            'clear_skipped' => $queue->clearStatus(ImportQueue::STATUS_SKIPPED),
            'clear_pending' => $queue->clearStatus(ImportQueue::STATUS_PENDING),
        };

        return back()->with('status', "{$count} kayıt için işlem tamamlandı.");
    }

    public function settings(Settings $settings)
    {
        return view('admin.settings', [
            'settings' => $settings->allPublic(),
            'raw' => [
                'site_name' => $settings->get('site_name', 'nozu.me'),
                'site_description' => $settings->get('site_description', 'nozu.me, Türkçe anime ve manga keşif veritabanıdır.'),
                'chrome_extension_url' => $settings->get('chrome_extension_url'),
                'favicon_path' => $settings->get('favicon_path'),
                'deepl_api_key' => $settings->get('deepl_api_key'),
                'google_translate_api_key' => $settings->get('google_translate_api_key'),
                'gemini_api_key' => $settings->get('gemini_api_key'),
                'translation_provider' => $settings->get('translation_provider', config('services.translation.provider', 'azure')),
                'translation_provider_chain' => $settings->get('translation_provider_chain', 'gemini,google,azure'),
                'translation_fallback' => $settings->get('translation_fallback', 'original'),
                'azure_translator_enabled' => $settings->get('azure_translator_enabled', '1'),
                'azure_translator_region' => $settings->get('azure_translator_region', config('services.azure_translator.region')),
                'azure_translator_endpoint' => $settings->get('azure_translator_endpoint', config('services.azure_translator.endpoint')),
                'azure_translator_key' => $settings->get('azure_translator_key'),
                'azure_translator_success_count' => $settings->get('azure_translator_success_count', '0'),
                'azure_translator_failed_count' => $settings->get('azure_translator_failed_count', '0'),
                'azure_translator_character_count' => $settings->get('azure_translator_character_count', '0'),
                'azure_translator_last_success_at' => $settings->get('azure_translator_last_success_at'),
                'azure_translator_last_error' => $settings->get('azure_translator_last_error'),
                'azure_translator_last_429_at' => $settings->get('azure_translator_last_429_at'),
                'deepl_endpoint_type' => $settings->get('deepl_endpoint_type', 'auto'),
                'deepl_enabled' => $settings->get('deepl_enabled', '0'),
                'google_translate_enabled' => $settings->get('google_translate_enabled', '0'),
                'gemini_enabled' => $settings->get('gemini_enabled', '0'),
            ],
        ]);
    }

    public function users(Settings $settings)
    {
        return view('admin.users-index', [
            'settings' => $settings->allPublic(),
            'users' => User::query()->latest()->paginate(30)->withQueryString(),
        ]);
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'in:user,admin,super_admin'],
        ]);

        if ($user->id === $request->user()->id && $validated['role'] === 'user') {
            return back()->withErrors(['role' => 'Kendi admin yetkini kaldıramazsın.']);
        }

        $user->update($validated);

        return back()->with('status', 'Kullanıcı rolü güncellendi.');
    }

    public function reports(Settings $settings)
    {
        return view('admin.reports-index', [
            'settings' => $settings->allPublic(),
            'reports' => Report::query()
                ->with(['user', 'reportable'])
                ->latest()
                ->paginate(30)
                ->withQueryString(),
        ]);
    }

    public function updateReport(Request $request, Report $report): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:open,reviewed,closed'],
        ]);

        $report->update($validated);

        return back()->with('status', 'Şikayet durumu güncellendi.');
    }

    public function destroyComment(Comment $comment): RedirectResponse
    {
        $comment->delete();

        return back()->with('status', 'Yorum silindi.');
    }

    public function saveSettings(Request $request, Settings $settings): RedirectResponse
    {
        $validated = $request->validate([
            'site_name' => ['required', 'string', 'max:80'],
            'site_description' => ['nullable', 'string', 'max:240'],
            'chrome_extension_url' => ['nullable', 'url', 'max:500'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'favicon' => ['nullable', 'image', 'max:512'],
            'translation_provider' => ['required', 'in:azure,deepl,google,gemini,none'],
            'translation_provider_chain' => ['nullable', 'string', 'max:120'],
            'translation_fallback' => ['required', 'in:fail,original,google,gemini,public_google'],
            'azure_translator_enabled' => ['nullable', 'boolean'],
            'azure_translator_region' => ['nullable', 'string', 'max:80'],
            'azure_translator_endpoint' => ['nullable', 'url', 'max:180'],
            'azure_translator_key' => ['nullable', 'string'],
            'deepl_endpoint_type' => ['required', 'in:auto,free,pro'],
            'deepl_api_key' => ['nullable', 'string'],
            'google_translate_api_key' => ['nullable', 'string'],
            'gemini_api_key' => ['nullable', 'string'],
            'deepl_enabled' => ['nullable', 'boolean'],
            'google_translate_enabled' => ['nullable', 'boolean'],
            'gemini_enabled' => ['nullable', 'boolean'],
        ]);

        if ($request->hasFile('logo')) {
            $validated['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        if ($request->hasFile('favicon')) {
            $validated['favicon_path'] = $request->file('favicon')->store('favicons', 'public');
        }

        unset($validated['logo'], $validated['favicon']);

        $validated['deepl_enabled'] = $request->boolean('deepl_enabled');
        $validated['azure_translator_enabled'] = $request->boolean('azure_translator_enabled');
        $validated['google_translate_enabled'] = $request->boolean('google_translate_enabled');
        $validated['gemini_enabled'] = $request->boolean('gemini_enabled');
        if (blank($validated['azure_translator_key'] ?? null)) {
            unset($validated['azure_translator_key']);
        }
        if (blank($validated['deepl_api_key'] ?? null)) {
            unset($validated['deepl_api_key']);
        }
        if (blank($validated['google_translate_api_key'] ?? null)) {
            unset($validated['google_translate_api_key']);
        }
        if (blank($validated['gemini_api_key'] ?? null)) {
            unset($validated['gemini_api_key']);
        }
        $settings->setMany($validated);

        return back()->with('status', 'Ayarlar kaydedildi.');
    }

    public function testTranslation(Request $request, \App\Services\TranslationService $translation): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'in:azure,deepl,google,gemini'],
        ]);

        $result = $translation->testProvider($validated['provider']);

        return back()->with(
            $result['ok'] ? 'translation_result' : 'translation_error',
            json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    private function queueValidation(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'in:anime,manga'],
            'sort' => ['required', 'in:POPULARITY_DESC,TRENDING_DESC,SCORE_DESC,START_DATE_DESC'],
            'per_page' => ['required', 'integer', 'min:1', 'max:50'],
            'pages' => ['required', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'genre' => ['nullable', 'string', 'max:80'],
            'year' => ['nullable', 'integer', 'min:1940', 'max:2100'],
            'season' => ['nullable', 'in:WINTER,SPRING,SUMMER,FALL'],
            'format' => ['nullable', 'string', 'max:40'],
            'links' => ['nullable', 'string', 'max:20000'],
        ]);
    }

    private function isAdmin(?string $role): bool
    {
        return in_array($role, ['super_admin', 'admin'], true);
    }

    private function recentLogs(): array
    {
        $files = [
            'Import' => 'import',
            'Scanner' => 'scanner',
            'Güvenlik' => 'security',
            'Laravel' => 'laravel',
        ];

        return collect($files)
            ->mapWithKeys(function (string $channel, string $name): array {
                $path = $this->latestLogPath($channel);

                if (! $path) {
                    return [$name => []];
                }

                $content = rescue(fn (): string => File::get($path), '', report: false);

                $lines = collect(preg_split('/\R/', $content) ?: [])
                    ->filter()
                    ->take(-40)
                    ->values()
                    ->all();

                return [$name => $lines];
            })
            ->all();
    }

    private function latestLogPath(string $channel): ?string
    {
        $single = storage_path("logs/{$channel}.log");

        if (File::exists($single)) {
            return $single;
        }

        $matches = File::glob(storage_path("logs/{$channel}-*.log"));

        return collect($matches)->sortDesc()->first();
    }

    private function tableCount(string $table): int
    {
        return Schema::hasTable($table) ? DB::table($table)->count() : 0;
    }
}
