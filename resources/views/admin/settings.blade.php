@extends('layouts.app')

@section('content')
    <section class="admin-bar">
        <div>
            <h1>Ayarlar</h1>
            <p>Logo ve otomatik çeviri ayarları.</p>
        </div>
        <a class="button" href="{{ route('admin.dashboard') }}">Panele dön</a>
    </section>

    <form class="settings-form" method="post" action="{{ route('admin.settings.save') }}" enctype="multipart/form-data">
        @csrf
        <section class="panel">
            <h2>Site</h2>
            <label>Site adı</label>
            <input name="site_name" value="{{ old('site_name', $raw['site_name']) }}" required>
            <label>Logo</label>
            <input type="file" name="logo" accept="image/*">
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

        <button class="button primary">Ayarları kaydet</button>
    </form>
@endsection
