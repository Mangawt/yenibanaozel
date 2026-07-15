<!doctype html>
<html lang="tr">
<head>
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
    <meta property="og:site_name" content="nozu.me">
    <meta property="og:title" content="{{ $seo['title'] }}">
    <meta property="og:description" content="{{ $seo['description'] }}">
    <meta property="og:type" content="{{ $seo['type'] }}">
    <meta property="og:url" content="{{ $seo['canonical'] }}">
    <meta property="og:image" content="{{ $seo['image'] }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seo['title'] }}">
    <meta name="twitter:description" content="{{ $seo['description'] }}">
    <meta name="twitter:image" content="{{ $seo['image'] }}">
    <script type="application/ld+json">{!! json_encode($seo['schema'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    <link rel="stylesheet" href="{{ asset('style.css') }}">
</head>
<body>
<header class="site-header">
    <a class="brand" href="{{ route('home') }}">
        @if(! empty($settings['logo_path']))
            <img src="{{ asset('storage/'.$settings['logo_path']) }}" alt="{{ $settings['site_name'] ?? 'nozu.me' }}">
        @else
            <span>N</span>
        @endif
        <strong>{{ $settings['site_name'] ?? 'nozu.me' }}</strong>
    </a>
    <nav>
        <a href="{{ route('home') }}">Ana Sayfa</a>
        <a href="{{ route('search', ['type' => 'anime']) }}">Anime</a>
        <a href="{{ route('search', ['type' => 'manga']) }}">Manga</a>
        <a href="{{ route('people.index') }}">Sanatçılar</a>
        <a href="{{ route('studios.index') }}">Stüdyolar</a>
        <a href="{{ route('api.docs') }}">API</a>
    </nav>
    <form class="quick-search autocomplete-wrap" action="{{ route('search') }}" method="get">
        <input class="js-autocomplete" type="search" name="q" placeholder="Anime veya manga ara" autocomplete="off">
        <button>Ara</button>
    </form>
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

<footer class="site-footer">
    <div class="footer-grid compact-footer">
        <div>
            <strong>{{ $settings['site_name'] ?? 'nozu.me' }}</strong>
            <p>{{ $settings['site_description'] ?? 'Türk kullanıcılar için hazırlanmış anime ve manga keşif veritabanı.' }}</p>
        </div>
        <div>
            <h3>Keşfet</h3>
            <a href="{{ route('search', ['type' => 'anime']) }}">Anime arşivi</a>
            <a href="{{ route('search', ['type' => 'manga']) }}">Manga arşivi</a>
            <a href="{{ route('people.index') }}">Sanatçılar</a>
            <a href="{{ route('studios.index') }}">Stüdyolar</a>
        </div>
        <div>
            <h3>nozu.me</h3>
            <a href="{{ route('api.docs') }}">Geliştirici API</a>
            <a href="{{ route('about') }}">Hakkımızda</a>
            <a href="{{ route('privacy') }}">Gizlilik Politikası</a>
        </div>
    </div>
    <div class="footer-bottom">
        <span>AniList API ile beslenmektedir.</span>
        <span>© {{ date('Y') }} nozu.me</span>
    </div>
</footer>
<script>
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
