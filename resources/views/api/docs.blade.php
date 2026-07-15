@extends('layouts.app')

@section('content')
    @php($apiBase = rtrim(config('nozu_openapi.servers.0.url', 'https://nozu.me/api/v1'), '/'))
    <section class="api-hero">
        <p class="eyebrow">nozu.me API v1</p>
        <h1>Ücretsiz anime ve manga REST API</h1>
        <p>Nozu API; mobil uygulama, Discord botu ve kişisel projeler için açık JSON endpointleri sunar. Anahtar veya başvuru gerekmez; standart response, pagination, fields/include ve HTTP cache desteği hazır gelir.</p>
        <div class="actions">
            <a class="button primary" href="#baslangic">Hemen kullan</a>
            <a class="button" href="{{ $apiBase }}/openapi.json">OpenAPI JSON</a>
        </div>
    </section>

    <nav class="api-doc-nav">
        <a href="#baslangic">Başlangıç</a>
        <a href="#response">Response</a>
        <a href="#search">Arama</a>
        <a href="#detail">Detay</a>
        <a href="#lookup">Çoklu kayıt</a>
        <a href="#cache">Cache</a>
    </nav>

    <section class="api-layout">
        <article class="endpoint-card" id="baslangic">
            <span class="method">GET</span>
            <h3>Başlangıç</h3>
            <p class="muted">Tüm public endpointler `/api/v1` altında çalışır. İsteklere API anahtarı eklemen gerekmez.</p>
            <pre><code>fetch('{{ $apiBase }}/latest')
  .then(response => response.json())
  .then(console.log)</code></pre>
        </article>

        <article class="endpoint-card" id="response">
            <h3>Standart response</h3>
            <pre><code>{
  "success": true,
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 24,
    "total": 120,
    "last_page": 5
  },
  "links": {
    "next": "..."
  }
}</code></pre>
        </article>

        <article class="endpoint-card" id="search">
            <span class="method">GET</span>
            <h3>/api/v1/search</h3>
            <p class="muted">Parametreler: type, q, genre, year, season, format, status, studio, country, adult, sort, page, per_page, minimum_score, maximum_score.</p>
            <pre><code>{{ $apiBase }}/search?type=manga&q=one&fields=title,cover_image,genres</code></pre>
        </article>

        <article class="endpoint-card" id="detail">
            <span class="method">GET</span>
            <h3>Detay ve include</h3>
            <p class="muted">Detay endpointlerinde büyük koleksiyonları yalnızca ihtiyaç duyduğunda isteyebilirsin.</p>
            <pre><code>{{ $apiBase }}/anime/ornek-slug?include=characters,relations,staff,recommendations</code></pre>
        </article>

        <article class="endpoint-card" id="lookup">
            <span class="method">GET/POST</span>
            <h3>Çoklu kayıt</h3>
            <pre><code>GET {{ $apiBase }}/media?ids=20,30,41

POST {{ $apiBase }}/media/batch
{"ids": [20, 30, 41]}</code></pre>
        </article>

        <article class="endpoint-card" id="cache">
            <h3>Cache ve hatalar</h3>
            <p class="muted">Yanıtlarda ETag, Last-Modified ve Cache-Control headerları bulunur. Public API limiti 60 istek/dakika/IP şeklindedir. Hatalar da aynı JSON formatıyla döner.</p>
            <pre><code>{
  "success": false,
  "message": "Kayıt bulunamadı.",
  "errors": []
}</code></pre>
        </article>
    </section>
@endsection
