<?php

namespace App\Http\Controllers;

use App\Models\ImportQueue;
use App\Models\Media;
use App\Services\ExternalMediaService;
use App\Services\ImportQueueService;
use App\Services\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function login()
    {
        return view('admin.login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        $configuredPassword = (string) config('services.admin.password', env('ADMIN_PASSWORD', 'Nasiptorun55.'));
        $password = $request->string('password')->value();
        $passwordMatches = str_starts_with($configuredPassword, '$2y$')
            ? Hash::check($password, $configuredPassword)
            : hash_equals($configuredPassword, $password);

        if (! $passwordMatches) {
            return back()->withErrors(['password' => 'Şifre hatalı.']);
        }

        $request->session()->put('adminasip', true);

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('adminasip');

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
        return view('admin.import-queue', [
            'settings' => $settings->allPublic(),
            'stats' => $queue->stats(),
            'preview' => $request->session()->get('import_queue_preview'),
            'items' => ImportQueue::query()->latest()->paginate(30),
        ]);
    }

    public function previewQueue(Request $request, ImportQueueService $queue): RedirectResponse
    {
        $validated = $this->queueValidation($request);
        $preview = $queue->preview($validated + ['source' => 'anilist']);

        $request->session()->put('import_queue_preview', $preview);

        return redirect()->route('admin.import-queue')->with('status', 'Keşif tamamlandı. Kuyruğa eklemeden önce özeti kontrol edebilirsin.');
    }

    public function enqueueQueue(Request $request, ImportQueueService $queue): RedirectResponse
    {
        $preview = $request->session()->pull('import_queue_preview');

        if (! $preview) {
            return back()->withErrors(['queue' => 'Önce keşif önizlemesi oluşturmalısın.']);
        }

        $result = $queue->enqueue($preview['options'], $preview['ids']);

        return redirect()
            ->route('admin.import-queue')
            ->with('status', "{$result['created']} kayıt kuyruğa eklendi, {$result['skipped']} kayıt atlandı.");
    }

    public function processQueue(ImportQueueService $queue): RedirectResponse
    {
        $result = $queue->process(1);

        return back()->with('status', "İşlenen: {$result['processed']} · Tamamlanan: {$result['completed']} · Atlanan: {$result['skipped']} · Hatalı: {$result['failed']}");
    }

    public function retryQueue(ImportQueue $queueItem, ImportQueueService $queue): RedirectResponse
    {
        $queue->retry($queueItem);

        return back()->with('status', 'Kayıt tekrar kuyruğa alındı.');
    }

    public function settings(Settings $settings)
    {
        return view('admin.settings', [
            'settings' => $settings->allPublic(),
            'raw' => [
                'site_name' => $settings->get('site_name', 'nozu.me'),
                'site_description' => $settings->get('site_description', 'nozu.me, Türkçe anime ve manga keşif veritabanıdır.'),
                'favicon_path' => $settings->get('favicon_path'),
                'deepl_api_key' => $settings->get('deepl_api_key'),
                'google_translate_api_key' => $settings->get('google_translate_api_key'),
                'gemini_api_key' => $settings->get('gemini_api_key'),
                'translation_provider' => $settings->get('translation_provider', 'deepl'),
                'deepl_enabled' => $settings->get('deepl_enabled', '0'),
                'google_translate_enabled' => $settings->get('google_translate_enabled', '0'),
                'gemini_enabled' => $settings->get('gemini_enabled', '0'),
            ],
        ]);
    }

    public function saveSettings(Request $request, Settings $settings): RedirectResponse
    {
        $validated = $request->validate([
            'site_name' => ['required', 'string', 'max:80'],
            'site_description' => ['nullable', 'string', 'max:240'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'favicon' => ['nullable', 'image', 'max:512'],
            'translation_provider' => ['required', 'in:deepl,google,gemini,none'],
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
        $validated['google_translate_enabled'] = $request->boolean('google_translate_enabled');
        $validated['gemini_enabled'] = $request->boolean('gemini_enabled');

        $settings->setMany($validated);

        return back()->with('status', 'Ayarlar kaydedildi.');
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
        ]);
    }
}
