@extends('layouts.app')

@section('content')
    <section class="admin-shell">
        <aside class="admin-sidebar">
            <strong>nozu.me CMS</strong>
            <a href="{{ route('admin.dashboard') }}">Genel Bakış</a>
            <a href="{{ route('admin.import-queue') }}">Import Queue</a>
            <a class="active" href="{{ route('admin.settings') }}">Ayarlar</a>
            <form method="post" action="{{ route('admin.logout') }}">@csrf<button>Çıkış</button></form>
        </aside>

        <form class="admin-main settings-form" method="post" action="{{ route('admin.settings.save') }}" enctype="multipart/form-data">
            @csrf
            <section class="admin-hero">
                <div>
                    <p class="eyebrow">Ayarlar</p>
                    <h1>Site ve çeviri</h1>
                    <p>Logo, favicon, açıklama ve otomatik çeviri sağlayıcılarını buradan yönet.</p>
                </div>
                <button class="button primary">Kaydet</button>
            </section>

            <section class="panel">
                <h2>Site</h2>
                <label>Site adı</label>
                <input name="site_name" value="{{ old('site_name', $raw['site_name']) }}" required>
                <label>Site açıklaması</label>
                <textarea name="site_description" rows="3" maxlength="240">{{ old('site_description', $raw['site_description']) }}</textarea>
                <label>Logo</label>
                <input type="file" name="logo" accept="image/*">
                <label>Favicon</label>
                <input type="file" name="favicon" accept="image/*">
            </section>

            <section class="panel">
                <h2>Çeviri</h2>
                <label>Aktif sağlayıcı</label>
                <select name="translation_provider">
                    <option value="deepl" @selected($raw['translation_provider'] === 'deepl')>DeepL</option>
                    <option value="google" @selected($raw['translation_provider'] === 'google')>Google Translate</option>
                    <option value="gemini" @selected($raw['translation_provider'] === 'gemini')>Gemini</option>
                    <option value="none" @selected($raw['translation_provider'] === 'none')>Çeviri kapalı</option>
                </select>

                <label class="check"><input type="checkbox" name="deepl_enabled" value="1" @checked($raw['deepl_enabled'] === '1')> DeepL aktif</label>
                <textarea name="deepl_api_key" rows="2" placeholder="DeepL API anahtarı">{{ old('deepl_api_key', $raw['deepl_api_key']) }}</textarea>

                <label class="check"><input type="checkbox" name="google_translate_enabled" value="1" @checked($raw['google_translate_enabled'] === '1')> Google Translate aktif</label>
                <textarea name="google_translate_api_key" rows="2" placeholder="Google Translate API anahtarı">{{ old('google_translate_api_key', $raw['google_translate_api_key']) }}</textarea>

                <label class="check"><input type="checkbox" name="gemini_enabled" value="1" @checked($raw['gemini_enabled'] === '1')> Gemini aktif</label>
                <textarea name="gemini_api_key" rows="2" placeholder="Gemini API anahtarı">{{ old('gemini_api_key', $raw['gemini_api_key']) }}</textarea>
            </section>
        </form>
    </section>
@endsection
