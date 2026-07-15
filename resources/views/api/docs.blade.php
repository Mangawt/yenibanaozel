@extends('layouts.app', ['settings' => ['site_name' => 'nozu.me']])

@section('content')
    <section class="api-hero">
        <p class="eyebrow">nozu.me API</p>
        <h1>Türkçe anime ve manga verisini uygulamana bağla</h1>
        <p>nozu.me API; Türkçeleştirilmiş tür/format/durum alanları, yerel görsel bağlantıları, karakterler, ilişkiler, ekip, etiketler, bağlantılar ve istatistiklerle JSON olarak sunar.</p>
    </section>

    <section class="docs-grid">
        <article class="panel docs">
            <h2>1. Arama</h2>
            <p>Başlık, tür ve sayfalama ile içerik arayabilirsin.</p>
            <pre><code>GET {{ url('/api/v1/search') }}?type=anime&q=naruto&per_page=24</code></pre>
        </article>

        <article class="panel docs">
            <h2>2. Detay</h2>
            <p>Slug ile tekil anime veya manga detayını çekersin.</p>
            <pre><code>GET {{ url('/api/v1/anime/anime-naruto-20') }}
GET {{ url('/api/v1/manga/{slug}') }}</code></pre>
        </article>

        <article class="panel docs">
            <h2>3. Kullanım limiti</h2>
            <p>Herkese açık API dakikada 30 istek sınırıyla çalışır.</p>
            <pre><code>HTTP 429 Too Many Requests</code></pre>
        </article>

        <article class="panel docs">
            <h2>4. Dönen ana alanlar</h2>
            <p>Yanıt; başlıklar, Türkçe özet, yerel görseller, türler, karakterler, ilişkiler, öneriler, ekip, resmi bağlantılar, yayın bağlantıları ve dağılım istatistiklerini içerir.</p>
            <pre><code>{
  "data": [{
    "type": "anime",
    "title": {"romaji": "Naruto", "native": "NARUTO -ナルト-"},
    "genres": ["Aksiyon", "Macera", "Dram"],
    "cover_image": "/storage/media/anime/20/covers/...",
    "characters": [],
    "relations": [],
    "rankings": [],
    "external_links": [],
    "stats": {}
  }]
}</code></pre>
        </article>
    </section>
@endsection
