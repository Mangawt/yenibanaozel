<!doctype html>
<html lang="tr">
<head>
    <meta name="google-site-verification" content="27R5jvqeI5wht6bD-5OWLaoFOQWVj7rIqGH8O6BDhis" />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $seo = \App\Support\Seo::defaults($seo ?? []);
    @endphp
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
        const nozuTheme = localStorage.getItem('nozu-theme') || 'light';
        document.documentElement.dataset.theme = nozuTheme;
        if (!localStorage.getItem('nozu-theme')) {
            localStorage.setItem('nozu-theme', 'light');
        }
    </script>
    <script type="application/ld+json">{!! json_encode($seo['schema'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    <link rel="preload" href="{{ asset('vendor/manrope/manrope-1.ttf') }}" as="font" type="font/ttf" crossorigin>
    <link rel="stylesheet" href="{{ asset('vendor/manrope/manrope.css') }}?v={{ @filemtime(public_path('vendor/manrope/manrope.css')) ?: time() }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}?v={{ @filemtime(public_path('vendor/fontawesome/css/all.min.css')) ?: time() }}">
    <link rel="stylesheet" href="{{ asset('style.css') }}?v={{ @filemtime(public_path('style.css')) ?: time() }}">
    <link rel="stylesheet" href="{{ asset('nozu-theme.css') }}?v={{ @filemtime(public_path('nozu-theme.css')) ?: time() }}">
</head>
<body>
<header class="site-header modern-header">
    <a class="brand" href="{{ route('home') }}" aria-label="{{ $settings['site_name'] ?? 'nozu.me' }}">
        @if(! empty($settings['logo_path']))
            <img class="brand-logo-wordmark" src="{{ asset('storage/'.$settings['logo_path']) }}" alt="{{ $settings['site_name'] ?? 'nozu.me' }}">
        @else
            <img class="brand-logo-wordmark" src="{{ asset('nozu-logo.svg') }}" alt="{{ $settings['site_name'] ?? 'nozu.me' }}">
        @endif
    </a>

    <button class="mobile-menu-toggle" type="button" aria-label="Menüyü aç" aria-expanded="false">
        <i class="fa-solid fa-bars"></i>
    </button>

    <nav class="main-nav">
        <a class="{{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">
            <i class="fa-solid fa-compass"></i><span>Keşfet</span>
        </a>
        <a class="{{ request()->routeIs('search') && request('type') === 'anime' ? 'active' : '' }}" href="{{ route('search', ['type' => 'anime']) }}">
            <i class="fa-solid fa-tv"></i><span>Anime</span>
        </a>
        <a class="{{ request()->routeIs('search') && request('type') === 'manga' ? 'active' : '' }}" href="{{ route('search', ['type' => 'manga']) }}">
            <i class="fa-solid fa-book-open"></i><span>Manga</span>
        </a>
        <a class="{{ request()->routeIs('characters.*') ? 'active' : '' }}" href="{{ route('characters.index') }}">
            <i class="fa-regular fa-user"></i><span>Karakterler</span>
        </a>
        <a class="{{ request()->routeIs('studios.*') ? 'active' : '' }}" href="{{ route('studios.index') }}">
            <i class="fa-solid fa-building"></i><span>Stüdyolar</span>
        </a>
        <a class="{{ request()->routeIs('people.*') ? 'active' : '' }}" href="{{ route('people.index') }}">
            <i class="fa-solid fa-user-group"></i><span>Kişiler</span>
        </a>
        <a class="{{ request()->routeIs('api.docs') ? 'active' : '' }}" href="{{ route('api.docs') }}">
            <i class="fa-solid fa-code"></i><span>API</span>
        </a>
    </nav>

    <form class="quick-search autocomplete-wrap" action="{{ route('search') }}" method="get">
        <input class="js-autocomplete" type="search" name="q" placeholder="Anime, manga veya karakter ara" autocomplete="off">
        <kbd>Ctrl K</kbd>
    </form>

    <div class="header-actions">
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
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button>Çıkış</button>
                    </form>
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
            <p>{{ $settings['site_description'] ?? 'Türk kullanıcılar için hazırlanmış anime ve manga keşif veritabanı.' }}</p>
            @php
                $chromeExtensionUrl = $settings['chrome_extension_url']
                    ?? 'https://chromewebstore.google.com/detail/nozu-anime-yard%C4%B1mc%C4%B1s%C4%B1/kmneeifhdnckmmhffhfejkcfgaicpfae';
            @endphp
            <a class="chrome-extension-card" href="{{ $chromeExtensionUrl }}" target="_blank" rel="noopener">
                <i class="fa-brands fa-chrome"></i>
                <span>Chrome eklentisi</span>
            </a>
            <span class="chrome-extension-card android-soon">
                <i class="fa-brands fa-android"></i>
                <span>Android Uygulaması Yakında</span>
            </span>
        </div>
        <nav class="footer-links" aria-label="Alt menü">
            <a href="{{ route('search', ['type' => 'anime']) }}">Anime</a>
            <a href="{{ route('search', ['type' => 'manga']) }}">Manga</a>
            <a href="{{ route('people.index') }}">Kişiler</a>
            <a href="{{ route('studios.index') }}">Stüdyolar</a>
            <a href="{{ route('api.docs') }}">API</a>
            <a href="{{ route('about') }}">Hakkımızda</a>
            <a href="{{ route('privacy') }}">Gizlilik Politikası</a>
            <a href="{{ route('terms') }}">Kullanım Şartları</a>
            <a href="{{ route('contact') }}">İletişim</a>
        </nav>
    </div>
    <div class="footer-bottom">
        <span></span>
        <span>© {{ date('Y') }} nozu.me</span>
    </div>
</footer>

<script>
    const mobileToggle = document.querySelector('.mobile-menu-toggle');
    const siteHeader = document.querySelector('.site-header');
    if (mobileToggle && siteHeader) {
        mobileToggle.addEventListener('click', () => {
            const open = siteHeader.classList.toggle('menu-open');
            mobileToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            mobileToggle.innerHTML = open ? '<i class="fa-solid fa-xmark"></i>' : '<i class="fa-solid fa-bars"></i>';
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
