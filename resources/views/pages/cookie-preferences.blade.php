@extends('layouts.app')

@section('content')
    <section class="legal-page legal-rich">
        <p class="eyebrow">Tercihler</p>
        <h1>Çerez Tercihleri</h1>
        <p>Şu an Nozu.me oturum, güvenlik ve tema tercihi gibi temel kayıtları kullanır. Zorunlu olmayan analitik veya pazarlama çerezleri eklenirse bu panel üzerinden yönetilebilir.</p>

        <div class="legal-highlight">
            <strong>Mevcut durum</strong>
            <span>Zorunlu olmayan pazarlama çerezleri varsayılan olarak aktif değildir.</span>
        </div>

        <div class="cookie-panel" data-cookie-panel>
            <label>
                <span>Zorunlu çerezler</span>
                <input type="checkbox" checked disabled>
            </label>
            <label>
                <span>Tercih çerezleri</span>
                <input type="checkbox" data-cookie-choice="preferences">
            </label>
            <label>
                <span>Analitik çerezler</span>
                <input type="checkbox" data-cookie-choice="analytics">
            </label>
            <label>
                <span>Pazarlama çerezleri</span>
                <input type="checkbox" data-cookie-choice="marketing">
            </label>
            <div class="actions">
                <button type="button" class="primary" data-cookie-save>Tercihleri kaydet</button>
                <button type="button" data-cookie-reject>Zorunlu olmayanları reddet</button>
            </div>
            <p class="muted" data-cookie-status></p>
        </div>
    </section>

    <script>
        const panel = document.querySelector('[data-cookie-panel]');
        const saved = JSON.parse(localStorage.getItem('nozu-cookie-preferences') || '{}');
        panel?.querySelectorAll('[data-cookie-choice]').forEach((input) => {
            input.checked = Boolean(saved[input.dataset.cookieChoice]);
        });
        panel?.querySelector('[data-cookie-save]')?.addEventListener('click', () => {
            const preferences = {};
            panel.querySelectorAll('[data-cookie-choice]').forEach((input) => {
                preferences[input.dataset.cookieChoice] = input.checked;
            });
            localStorage.setItem('nozu-cookie-preferences', JSON.stringify(preferences));
            panel.querySelector('[data-cookie-status]').textContent = 'Tercihler kaydedildi.';
        });
        panel?.querySelector('[data-cookie-reject]')?.addEventListener('click', () => {
            panel.querySelectorAll('[data-cookie-choice]').forEach((input) => input.checked = false);
            localStorage.setItem('nozu-cookie-preferences', JSON.stringify({
                preferences: false,
                analytics: false,
                marketing: false,
            }));
            panel.querySelector('[data-cookie-status]').textContent = 'Zorunlu olmayan çerezler reddedildi.';
        });
    </script>
@endsection
