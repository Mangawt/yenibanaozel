@extends('layouts.admin')

@section('title', 'Ayarlar - Nozu CMS')

@section('content')
    <section class="admin-shell">
        @include('admin.partials.sidebar')

        <div class="admin-main">
            <form id="settings-form" class="settings-form" method="post" action="{{ route('admin.settings.save') }}" enctype="multipart/form-data">
                @csrf
                <section class="admin-hero">
                    <div>
                        <p class="eyebrow">Ayarlar</p>
                        <h1>Site ve çeviri</h1>
                        <p>Logo, favicon ve otomatik çeviri sağlayıcılarını buradan yönet.</p>
                    </div>
                    <button class="button primary" form="settings-form">Kaydet</button>
                </section>

                <section class="panel">
                    <h2>Site</h2>
                    <label>Site adı</label>
                    <input name="site_name" value="{{ old('site_name', $raw['site_name']) }}" required>
                    <label>Site açıklaması</label>
                    <textarea name="site_description" rows="3" maxlength="240">{{ old('site_description', $raw['site_description']) }}</textarea>
                    <label>Chrome eklentisi linki</label>
                    <input name="chrome_extension_url" value="{{ old('chrome_extension_url', $raw['chrome_extension_url']) }}" placeholder="https://chromewebstore.google.com/...">
                    <label>Logo</label>
                    <input type="file" name="logo" accept="image/*">
                    <label>Favicon</label>
                    <input type="file" name="favicon" accept="image/*">
                </section>

                <section class="panel">
                    <h2>Çeviri</h2>
                    <label>Aktif sağlayıcı</label>
                    <select name="translation_provider">
                        <option value="azure" @selected($raw['translation_provider'] === 'azure')>Azure Translator</option>
                        <option value="deepl" @selected($raw['translation_provider'] === 'deepl')>DeepL</option>
                        <option value="google" @selected($raw['translation_provider'] === 'google')>Google Translate</option>
                        <option value="gemini" @selected($raw['translation_provider'] === 'gemini')>Gemini</option>
                        <option value="none" @selected($raw['translation_provider'] === 'none')>Çeviri kapalı</option>
                    </select>
                    <label>Çeviri sırası</label>
                    <input name="translation_provider_chain" value="{{ old('translation_provider_chain', $raw['translation_provider_chain'] ?? 'gemini,google,azure') }}" placeholder="gemini,google,azure">
                    <p class="muted">Bir sağlayıcı kota, bağlantı veya servis hatası verirse sıradaki sağlayıcı denenir. Önerilen sıra: Gemini Flash → Google Translate → Azure Translator.</p>

                    <div class="provider-card">
                        <div class="provider-card-head">
                            <div>
                                <h3>Azure Translator</h3>
                                <p>Azure bağlantısını kısa bir çeviri isteğiyle doğrula.</p>
                            </div>
                            <button class="button" type="submit" form="test-azure-form">
                                <i class="fa-solid fa-vial"></i> Azure test et
                            </button>
                        </div>
                        <label class="check"><input type="checkbox" name="azure_translator_enabled" value="1" @checked(($raw['azure_translator_enabled'] ?? '1') === '1')> Azure Translator aktif</label>
                        <input name="azure_translator_region" value="{{ old('azure_translator_region', $raw['azure_translator_region'] ?? '') }}" placeholder="Azure region">
                        <input name="azure_translator_endpoint" value="{{ old('azure_translator_endpoint', $raw['azure_translator_endpoint'] ?? 'https://api.cognitive.microsofttranslator.com') }}" placeholder="Azure endpoint">
                        <textarea name="azure_translator_key" rows="2" placeholder="{{ ! empty($raw['azure_translator_key']) ? 'Azure anahtarı kayıtlı. Değiştirmek için yeni anahtar yaz.' : 'Azure Translator anahtarı' }}"></textarea>
                        <div class="metric-grid compact">
                            <article><span>Azure başarılı</span><strong>{{ number_format((int) ($raw['azure_translator_success_count'] ?? 0), 0, ',', '.') }}</strong></article>
                            <article><span>Azure hatalı</span><strong>{{ number_format((int) ($raw['azure_translator_failed_count'] ?? 0), 0, ',', '.') }}</strong></article>
                            <article><span>Karakter</span><strong>{{ number_format((int) ($raw['azure_translator_character_count'] ?? 0), 0, ',', '.') }}</strong></article>
                            <article><span>Son başarı</span><strong>{{ $raw['azure_translator_last_success_at'] ?: '-' }}</strong></article>
                            <article><span>Son hata</span><strong>{{ $raw['azure_translator_last_error'] ?: '-' }}</strong></article>
                            <article><span>Son 429</span><strong>{{ $raw['azure_translator_last_429_at'] ?: '-' }}</strong></article>
                        </div>
                    </div>

                    <label>Fallback davranışı</label>
                    <select name="translation_fallback">
                        <option value="original" @selected($raw['translation_fallback'] === 'original')>Hata olursa orijinal metni sakla</option>
                        <option value="fail" @selected($raw['translation_fallback'] === 'fail')>Hata olursa import failed olsun</option>
                        <option value="google" @selected($raw['translation_fallback'] === 'google')>Google Translate'e geç</option>
                        <option value="gemini" @selected($raw['translation_fallback'] === 'gemini')>Gemini'ye geç</option>
                        <option value="public_google" @selected($raw['translation_fallback'] === 'public_google')>Public Google fallback kullan</option>
                    </select>

                    <div class="provider-card">
                        <div class="provider-card-head">
                            <div>
                                <h3>DeepL</h3>
                                <p>DeepL anahtarını ve endpoint seçimini doğrula.</p>
                            </div>
                            <button class="button" type="submit" form="test-deepl-form">
                                <i class="fa-solid fa-vial"></i> DeepL test et
                            </button>
                        </div>
                        <label class="check"><input type="checkbox" name="deepl_enabled" value="1" @checked($raw['deepl_enabled'] === '1')> DeepL aktif</label>
                        <select name="deepl_endpoint_type">
                            <option value="auto" @selected($raw['deepl_endpoint_type'] === 'auto')>DeepL endpoint otomatik seçilsin</option>
                            <option value="free" @selected($raw['deepl_endpoint_type'] === 'free')>DeepL Free endpoint</option>
                            <option value="pro" @selected($raw['deepl_endpoint_type'] === 'pro')>DeepL Pro endpoint</option>
                        </select>
                        <textarea name="deepl_api_key" rows="2" placeholder="DeepL API anahtarı">{{ old('deepl_api_key', $raw['deepl_api_key']) }}</textarea>
                    </div>

                    <label class="check"><input type="checkbox" name="google_translate_enabled" value="1" @checked($raw['google_translate_enabled'] === '1')> Google Translate aktif</label>
                    <textarea name="google_translate_api_key" rows="2" placeholder="Google Translate API anahtarı">{{ old('google_translate_api_key', $raw['google_translate_api_key']) }}</textarea>

                    <label class="check"><input type="checkbox" name="gemini_enabled" value="1" @checked($raw['gemini_enabled'] === '1')> Gemini aktif</label>
                    <textarea name="gemini_api_key" rows="2" placeholder="Gemini API anahtarı">{{ old('gemini_api_key', $raw['gemini_api_key']) }}</textarea>
                </section>
            </form>

            <section class="panel">
                <h2>Çeviri servis testleri</h2>
                @if(session('translation_error'))
                    <pre class="test-result error">{{ session('translation_error') }}</pre>
                @endif
                @if(session('translation_result'))
                    <pre class="test-result">{{ session('translation_result') }}</pre>
                @endif
                <div class="test-grid">
                    @foreach(['azure' => 'Azure', 'deepl' => 'DeepL', 'google' => 'Google Translate', 'gemini' => 'Gemini'] as $provider => $label)
                        <form id="test-{{ $provider }}-form" method="post" action="{{ route('admin.settings.translation.test') }}">
                            @csrf
                            <input type="hidden" name="provider" value="{{ $provider }}">
                            <button class="button">{{ $label }} test et</button>
                        </form>
                    @endforeach
                </div>
            </section>
        </div>
    </section>
@endsection
