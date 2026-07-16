<!doctype html>
<html lang="tr">
<head>
    <meta name="google-site-verification" content="27R5jvqeI5wht6bD-5OWLaoFOQWVj7rIqGH8O6BDhis" />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php($seo = \App\Support\Seo::defaults($seo ?? []))
    <title>{{ $seo['title'] }}</title>
    <meta name="description" content="{{ $seo['description'] }}">
    <meta name="robots" content="{{ $seo['robots'] }}">
    <link rel="canonical" href="{{ $seo['canonical'] }}">
    @if(! empty($settings['favicon_path']))
        <link rel="icon" href="{{ asset('storage/'.$settings['favicon_path']) }}">
    @else
        <link rel="icon" href="{{ asset('favicon.ico') }}">
    @endif
    <meta property="og:site_name" content="{{ $settings['site_name'] ?? 'nozu.me' }}">
    <meta property="og:title" content="{{ $seo['title'] }}">
    <meta property="og:description" content="{{ $seo['description'] }}">
    <meta property="og:type" content="{{ $seo['type'] }}">
    <meta property="og:url" content="{{ $seo['canonical'] }}">
    <meta property="og:image" content="{{ $seo['image'] }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seo['title'] }}">
    <meta name="twitter:description" content="{{ $seo['description'] }}">
    <meta name="twitter:image" content="{{ $seo['image'] }}">
    <script>
        const savedTheme = localStorage.getItem('nozu-theme') || '{{ auth()->user()->theme ?? 'system' }}';
        document.documentElement.dataset.theme = savedTheme;
    </script>
    <script type="application/ld+json">{!! json_encode($seo['schema'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="{{ asset('style.css') }}">
</head>
<body>
<header class="site-header modern-header">
    <a class="brand" href="{{ route('home') }}" aria-label="{{ $settings['site_name'] ?? 'nozu.me' }}">
        @if(! empty($settings['logo_path']))
            <img src="{{ asset('storage/'.$settings['logo_path']) }}" alt="{{ $settings['site_name'] ?? 'nozu.me' }}">
        @else
            <span>N</span>
        @endif
    </a>
    <nav class="main-nav">
        <a href="{{ route('home') }}">Keşfet</a>
        <a href="{{ route('search', ['type' => 'anime']) }}">Anime</a>
        <a href="{{ route('search', ['type' => 'manga']) }}">Manga</a>
        <a href="{{ route('people.index') }}">Karakterler</a>
        <a href="{{ route('studios.index') }}">Stüdyolar</a>
        <a href="{{ route('people.index') }}">Kişiler</a>
        <a href="{{ route('api.docs') }}">API</a>
    </nav>
    <form class="quick-search autocomplete-wrap" action="{{ route('search') }}" method="get">
        <input class="js-autocomplete" type="search" name="q" placeholder="Ara..." autocomplete="off">
        <kbd>Ctrl K</kbd>
    </form>
    <div class="header-actions">
        <button class="theme-toggle" type="button" aria-label="Tema seçimi" title="Tema">
            <span data-theme-icon>☾</span>
        </button>
        @auth
            <div class="user-menu">
                <button class="avatar-link" type="button" aria-label="Profil menüsü">
                    @if(auth()->user()->avatar_path)
                        <img src="{{ asset('storage/'.auth()->user()->avatar_path) }}" alt="{{ auth()->user()->username }}">
                    @else
                        <span>{{ mb_substr(auth()->user()->username ?: 'N', 0, 1) }}</span>
                    @endif
                </button>
                <div class="user-dropdown">
                    <a href="{{ route('profile.edit') }}">Profil</a>
                    <a href="{{ route('profile.list') }}">Okuma listem</a>
                    <form method="post" action="{{ route('logout') }}">@csrf<button>Çıkış</button></form>
                </div>
            </div>
        @else
            <a class="button ghost" href="{{ route('login') }}">Giriş</a>
        @endauth
    </div>
</header>

@if(session('status'))
    <div class="notice">{{ session('status') }}</div>
@endif

@if($errors->any())
    <div class="notice error">{{ $errors->first() }}</div>
@endif

<main class="page">
    @yield('content')
</main>

<footer class="site-footer minimal-footer">
    <div class="footer-main">
        <div class="footer-brand">
            <strong>{{ $settings['site_name'] ?? 'nozu.me' }}</strong>
            <p>{{ $settings['site_description'] ?? 'Türk kullanıcılar için hazırlanmış anime ve manga keşif veritabanı.' }}</p>
        </div>
        <nav class="footer-links" aria-label="Alt menü">
            <a href="{{ route('search', ['type' => 'anime']) }}">Anime</a>
            <a href="{{ route('search', ['type' => 'manga']) }}">Manga</a>
            <a href="{{ route('people.index') }}">Kişiler</a>
            <a href="{{ route('studios.index') }}">Stüdyolar</a>
            <a href="{{ route('api.docs') }}">API</a>
            <a href="{{ route('about') }}">Hakkımızda</a>
            <a href="{{ route('privacy') }}">Gizlilik</a>
            <a href="{{ route('terms') }}">Şartlar</a>
            <a href="{{ route('contact') }}">İletişim</a>
        </nav>
    </div>
    <div class="footer-bottom">
        <span></span>
        <span>© {{ date('Y') }} nozu.me</span>
    </div>
</footer>
<script>
    const themeButton = document.querySelector('.theme-toggle');
    const themeIcon = document.querySelector('[data-theme-icon]');
    const themes = ['dark', 'light', 'system'];
    const icons = {dark: '☾', light: '☀', system: '◐'};
    const labels = {dark: 'Karanlık tema', light: 'Aydınlık tema', system: 'Sistem teması'};
    const applyTheme = (theme) => {
        document.documentElement.dataset.theme = theme;
        localStorage.setItem('nozu-theme', theme);
        if (themeIcon) themeIcon.textContent = icons[theme] || icons.system;
        if (themeButton) {
            themeButton.title = labels[theme] || labels.system;
            themeButton.setAttribute('aria-label', labels[theme] || labels.system);
        }
    };
    if (themeButton) {
        applyTheme(localStorage.getItem('nozu-theme') || document.documentElement.dataset.theme || 'system');
        themeButton.addEventListener('click', () => {
            const current = document.documentElement.dataset.theme || 'system';
            applyTheme(themes[(themes.indexOf(current) + 1) % themes.length]);
        });
    }

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            document.querySelector('.quick-search input')?.focus();
        }
    });

    document.querySelectorAll('.js-autocomplete').forEach((input) => {
        const wrap = input.closest('.autocomplete-wrap') || input.parentElement;
        const box = document.createElement('div');
        box.className = 'autocomplete-box';
        wrap.classList.add('autocomplete-wrap');
        wrap.appendChild(box);

        let timer;
        input.addEventListener('input', () => {
            clearTimeout(timer);
            const q = input.value.trim();
            if (!q) {
                box.innerHTML = '';
                return;
            }

            timer = setTimeout(async () => {
                const response = await fetch(`{{ route('autocomplete') }}?q=${encodeURIComponent(q)}`);
                const items = await response.json();
                box.innerHTML = items.map((item) => `
                    <a href="${item.url}">
                        ${item.cover_image ? `<img src="${item.cover_image}" alt="">` : ''}
                        <span><strong>${item.title}</strong><small>${item.type === 'anime' ? 'Anime' : 'Manga'}</small></span>
                    </a>
                `).join('');
            }, 180);
        });

        document.addEventListener('click', (event) => {
            if (!wrap.contains(event.target)) {
                box.innerHTML = '';
            }
        });
    });
</script>
</body>
</html>
