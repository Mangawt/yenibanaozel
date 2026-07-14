<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Services\ExternalMediaService;
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

        $configuredPassword = (string) config('services.admin.password', env('ADMIN_PASSWORD', 'adminasip'));
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

        return back()->with('status', "{$result['count']} içerik AniList üzerinden toplu aktarıldı.");
    }

    public function settings(Settings $settings)
    {
        return view('admin.settings', [
            'settings' => $settings->allPublic(),
            'raw' => [
                'site_name' => $settings->get('site_name', 'nozu.me'),
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
            'logo' => ['nullable', 'image', 'max:2048'],
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

        unset($validated['logo']);

        $validated['deepl_enabled'] = $request->boolean('deepl_enabled');
        $validated['google_translate_enabled'] = $request->boolean('google_translate_enabled');
        $validated['gemini_enabled'] = $request->boolean('gemini_enabled');

        $settings->setMany($validated);

        return back()->with('status', 'Ayarlar kaydedildi.');
    }
}
