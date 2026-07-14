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
        <a href="{{ route('home') }}">Keşfet</a>
        <a href="{{ route('search', ['type' => 'anime']) }}">Anime</a>
        <a href="{{ route('search', ['type' => 'manga']) }}">Manga</a>
        <a href="{{ route('api.docs') }}">API</a>
    </nav>
    <form class="quick-search" action="{{ route('search') }}" method="get">
        <input type="search" name="q" placeholder="Anime veya manga ara">
        <button>Ara</button>
    </form>
</header>

@if(session('status'))
    <div class="notice">{{ session('status') }}</div>
@endif

<main class="page">
    @yield('content')
</main>

<footer class="site-footer">
    <div class="footer-grid">
        <div>
            <strong>{{ $settings['site_name'] ?? 'nozu.me' }}</strong>
            <p>Türk kullanıcılar için hazırlanmış anime ve manga keşif arşivi. İçerikler Türkçe etiketler, açıklamalar, karakterler ve bağlantılarla zenginleştirilir.</p>
        </div>
        <div>
            <h3>Keşfet</h3>
            <a href="{{ route('search', ['type' => 'anime']) }}">Anime arşivi</a>
            <a href="{{ route('search', ['type' => 'manga']) }}">Manga arşivi</a>
            <a href="{{ route('api.docs') }}">Geliştirici API</a>
            <a href="{{ route('about') }}">Hakkımızda</a>
            <a href="{{ route('privacy') }}">Gizlilik Politikası</a>
        </div>
        <div>
            <h3>Hakkında</h3>
            <p>nozu.me, keşif odaklı bir veritabanıdır; görselleri ve Türkçe düzenlenmiş alanları kendi API’siyle sunar.</p>
        </div>
    </div>
    <div class="footer-bottom">
        <span>AniList API ile beslenmektedir.</span>
        <span>© {{ date('Y') }} nozu.me</span>
    </div>
</footer>
</body>
</html>
