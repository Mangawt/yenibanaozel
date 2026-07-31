@extends('layouts.app')

@section('content')
    @php
        $apiBase = rtrim(config('nozu_openapi.servers.0.url', 'https://nozu.me/api/v1'), '/');
        $sections = [
            'genel-bakis' => 'Genel Bakış',
            'response' => 'Yanıt Yapısı',
            'listeleme' => 'Listeleme',
            'detay' => 'Anime ve Manga',
            'katalog' => 'Katalog',
            'kurallar' => 'Kurallar',
            'kaynaklar' => 'Kaynaklar',
        ];

        $commonListParams = [
            ['type', 'opsiyonel', 'anime, manga', 'Medya türü.'],
            ['q', 'opsiyonel', 'string, max:120', 'Başlık araması.'],
            ['genre', 'opsiyonel', 'string, max:80', 'Tür filtresi.'],
            ['year', 'opsiyonel', 'integer, 1900-2100', 'Başlangıç yılı.'],
            ['season', 'opsiyonel', 'string, max:40', 'Sezon adı.'],
            ['format', 'opsiyonel', 'string, max:40', 'TV, Movie, Manga gibi format.'],
            ['status', 'opsiyonel', 'string, max:40', 'Yayın durumu.'],
            ['studio', 'opsiyonel', 'string, max:120', 'Stüdyo veya yapımcı adı.'],
            ['country', 'opsiyonel', 'string, max:8', 'Ülke kodu.'],
            ['adult', 'opsiyonel', 'boolean', 'Yetişkin içerik filtresi.'],
            ['minimum_score', 'opsiyonel', 'integer, 0-100', 'Minimum ortalama puan.'],
            ['maximum_score', 'opsiyonel', 'integer, 0-100', 'Maksimum ortalama puan.'],
            ['sort', 'opsiyonel', 'score, score_desc, score_asc, popularity, popular, popularity_desc, latest, created_desc, oldest, title, start_date', 'Sıralama.'],
            ['page', 'opsiyonel', 'integer, min:1', 'Sayfa numarası.'],
            ['per_page', 'opsiyonel', 'integer, 1-50', 'Sayfa başına kayıt. Varsayılan 24.'],
            ['fields', 'opsiyonel', 'string, max:300', 'Virgülle ayrılmış alan listesi. Sadece mevcut response alanları döner.'],
            ['include', 'opsiyonel', 'string, max:300', 'characters, relations, recommendations, staff, external_links, streaming_episodes, tags, rankings, stats.'],
        ];

        $catalogEndpoints = [
            ['GET', '/studios', 'Stüdyo listesi', 'Stüdyo adı, slug, medya sayısı ve örnek görsel döndürür.'],
            ['GET', '/studios/{slug}', 'Stüdyo detayı', 'Stüdyo bilgisi ve bağlı medya kayıtlarını döndürür.'],
            ['GET', '/people', 'Kişi listesi', 'Seslendirme sanatçıları ve ekip kişilerini listeler.'],
            ['GET', '/people/{slug}', 'Kişi detayı', 'Kişi bilgisi ve bağlı kredileri döndürür.'],
            ['GET', '/characters/{slug}', 'Karakter detayı', 'Karakter bilgisi ve bağlı medya kredilerini döndürür.'],
        ];
    @endphp

    <main class="nozu-api-docs">
        <aside class="api-docs-sidebar">
            <a class="api-docs-brand" href="{{ route('api.docs') }}">
                <i class="fa-solid fa-code"></i>
                <span>Nozu API</span>
            </a>

            <nav aria-label="API dokümantasyon menüsü">
                @foreach($sections as $id => $label)
                    <a href="#{{ $id }}">{{ $label }}</a>
                @endforeach
            </nav>

            <div class="api-docs-side-note">
                <span>Public katalog API</span>
                <strong>Salt okunur endpointler</strong>
            </div>
        </aside>

        <div class="api-docs-content">
            <section class="api-docs-hero" id="genel-bakis">
                <div>
                    <span class="api-docs-eyebrow">nozu.me API v1</span>
                    <h1>Anime ve manga katalog verileri için public REST API.</h1>
                    <p>Nozu API dokümantasyonu yalnızca harici geliştiricilerin güvenle kullanabileceği salt okunur katalog endpointlerini kapsar. Kullanıcı hesabı, yorum, takip, bildirim ve sosyal işlem endpointleri public dokümana dahil edilmez.</p>
                </div>

                <div class="api-docs-hero-actions">
                    <a class="button primary" href="{{ $apiBase }}/openapi.json" target="_blank" rel="noopener">
                        <i class="fa-solid fa-file-code"></i> OpenAPI JSON
                    </a>
                    <a class="button ghost" href="#listeleme">
                        <i class="fa-solid fa-compass"></i> Endpointler
                    </a>
                </div>
            </section>

            <section class="api-docs-card">
                <div class="api-docs-card-head">
                    <span>Base URL</span>
                    <code>{{ $apiBase }}</code>
                </div>
                <p>Public katalog endpointleri API anahtarı gerektirmez. Public uygulamalarda Nozu atfı görünür olmalıdır.</p>
                <div class="api-code">
                    <button type="button" class="api-copy">Kopyala</button>
                    <pre><code>curl "{{ $apiBase }}/latest?type=anime&per_page=5"</code></pre>
                </div>
            </section>

            <section class="api-docs-grid" id="response">
                <article class="api-docs-card">
                    <div class="api-docs-card-head">
                        <span>Başarılı cevap</span>
                        <em>200 OK</em>
                    </div>
                    <div class="api-code">
                        <button type="button" class="api-copy">Kopyala</button>
                        <pre><code>{
  "success": true,
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 24,
    "total": 120,
    "last_page": 5,
    "from": 1,
    "to": 24
  },
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  }
}</code></pre>
                    </div>
                </article>

                <article class="api-docs-card">
                    <div class="api-docs-card-head">
                        <span>Hata cevapları</span>
                        <em>404 / 422 / 429</em>
                    </div>
                    <div class="api-code">
                        <button type="button" class="api-copy">Kopyala</button>
                        <pre><code>{
  "success": false,
  "message": "Kayıt bulunamadı.",
  "errors": []
}</code></pre>
                    </div>
                    <p>Yanlış parametrelerde 422, bulunamayan slug değerlerinde 404, limit aşımında 429 beklenebilir. 429 yanıtında gelen Retry-After başlığına uyulmalıdır.</p>
                </article>
            </section>

            <section class="api-docs-section" id="listeleme">
                <div class="api-docs-section-title">
                    <span>01</span>
                    <div>
                        <h2>Listeleme ve keşif endpointleri</h2>
                        <p>Arama, keşif, trend, popüler, sezon popülerleri, son eklenenler, rastgele kayıt ve otomatik tamamlama endpointleri.</p>
                    </div>
                </div>

                <article class="api-docs-card">
                    <div class="api-docs-card-head">
                        <span><b class="api-method get">GET</b> /search</span>
                        <em>Sayfalı liste</em>
                    </div>
                    <p>Anime ve mangaları doğrulanmış filtrelerle arar. Liste response alanları varsayılan olarak hafif tutulur; ağır koleksiyonlar için include kullanılır.</p>
                    <div class="api-param-table api-param-table-wide">
                        @foreach($commonListParams as [$name, $required, $allowed, $description])
                            <span>{{ $name }}</span><em>{{ $required }}</em><code>{{ $allowed }}</code><p>{{ $description }}</p>
                        @endforeach
                    </div>
                    <div class="api-code-grid">
                        <div class="api-code">
                            <button type="button" class="api-copy">Kopyala</button>
                            <pre><code>curl "{{ $apiBase }}/search?type=anime&q=jujutsu&genre=Aksiyon&year=2020&sort=popularity&per_page=12"</code></pre>
                        </div>
                        <div class="api-code">
                            <button type="button" class="api-copy">Kopyala</button>
                            <pre><code>const res = await fetch('{{ $apiBase }}/search?type=manga&q=one&fields=id,slug,title,cover_image');
const json = await res.json();</code></pre>
                        </div>
                    </div>
                </article>

                <div class="api-endpoint-list">
                    <article>
                        <span class="api-method get">GET</span>
                        <div>
                            <h3>/discover</h3>
                            <p>Keşif ekranı için slider, popüler anime/manga, son eklenenler, gizli cevherler ve rastgele kayıt blokları döndürür. Parametre gerektirmez.</p>
                            <code>{{ $apiBase }}/discover</code>
                        </div>
                    </article>
                    <article>
                        <span class="api-method get">GET</span>
                        <div>
                            <h3>/trending, /popular, /latest</h3>
                            <p>/search ile aynı validasyon kurallarını kullanır; sadece varsayılan sıralama değiştirilir.</p>
                            <code>{{ $apiBase }}/trending?type=anime&per_page=12</code>
                        </div>
                    </article>
                    <article>
                        <span class="api-method get">GET</span>
                        <div>
                            <h3>/season-popular</h3>
                            <p>Güncel sezonun popüler animelerini döndürür. per_page 1-50 aralığındadır, varsayılan 12’dir.</p>
                            <code>{{ $apiBase }}/season-popular?per_page=12</code>
                        </div>
                    </article>
                    <article>
                        <span class="api-method get">GET</span>
                        <div>
                            <h3>/random</h3>
                            <p>Rastgele tek medya döndürür. Opsiyonel type=anime veya type=manga filtresi kullanılabilir.</p>
                            <code>{{ $apiBase }}/random?type=manga</code>
                        </div>
                    </article>
                    <article>
                        <span class="api-method get">GET</span>
                        <div>
                            <h3>/autocomplete</h3>
                            <p>Başlık önek araması yapar. q zorunludur, maksimum 80 karakterdir ve en fazla 10 sonuç döner.</p>
                            <code>{{ $apiBase }}/autocomplete?q=nar</code>
                        </div>
                    </article>
                </div>
            </section>

            <section class="api-docs-section" id="detay">
                <div class="api-docs-section-title">
                    <span>02</span>
                    <div>
                        <h2>Anime ve manga detayları</h2>
                        <p>Tekil kayıtlar, öneriler ve toplu medya sorguları. Slug değerleri Nozu web URL’lerinde görünen slug ile aynıdır.</p>
                    </div>
                </div>

                <div class="api-endpoint-list compact">
                    <article>
                        <span class="api-method get">GET</span>
                        <div>
                            <h3>/anime/{slug}</h3>
                            <p>Anime detayını döndürür.</p>
                            <code>{{ $apiBase }}/anime/ornek-anime-slug?include=characters,relations,staff</code>
                        </div>
                    </article>
                    <article>
                        <span class="api-method get">GET</span>
                        <div>
                            <h3>/manga/{slug}</h3>
                            <p>Manga detayını döndürür.</p>
                            <code>{{ $apiBase }}/manga/ornek-manga-slug?fields=id,slug,title,genres</code>
                        </div>
                    </article>
                    <article>
                        <span class="api-method get">GET</span>
                        <div>
                            <h3>/recommendations/{slug}</h3>
                            <p>Benzer kayıtları döndürür. limit 1-50 aralığında kullanılabilir, varsayılan 12’dir.</p>
                            <code>{{ $apiBase }}/recommendations/ornek-slug?limit=8</code>
                        </div>
                    </article>
                    <article>
                        <span class="api-method get">GET</span>
                        <div>
                            <h3>/media?ids=20,30,41</h3>
                            <p>Nozu ID veya desteklenen kaynak ID değerleriyle çoklu kayıt döndürür. İlk 100 ID işlenir.</p>
                            <code>{{ $apiBase }}/media?ids=20,30,41</code>
                        </div>
                    </article>
                    <article>
                        <span class="api-method post">POST</span>
                        <div>
                            <h3>/media/batch</h3>
                            <p>JSON body ile en fazla 100 integer ID gönderilebilir.</p>
                            <code>{"ids": [20, 30, 41]}</code>
                        </div>
                    </article>
                </div>

                <article class="api-docs-card">
                    <div class="api-docs-card-head">
                        <span>Response alanları</span>
                        <em>fields / include</em>
                    </div>
                    <p>Temel alanlar: id, type, slug, title, description, cover_image, banner_image, format, status, average_score, mean_score, popularity, genres, season, season_year, start_year, updated_at, url.</p>
                    <p>Ağır include alanları: characters, relations, recommendations, staff, external_links, streaming_episodes, tags, rankings, stats.</p>
                </article>
            </section>

            <section class="api-docs-section" id="katalog">
                <div class="api-docs-section-title">
                    <span>03</span>
                    <div>
                        <h2>Katalog endpointleri</h2>
                        <p>Karakter, kişi ve stüdyo katalog verileri salt okunur olarak sunulur. Kullanıcı profili veya sosyal veri içermez.</p>
                    </div>
                </div>

                <div class="api-endpoint-list">
                    @foreach($catalogEndpoints as [$method, $path, $title, $description])
                        <article>
                            <span class="api-method {{ strtolower($method) }}">{{ $method }}</span>
                            <div>
                                <h3>{{ $path }} - {{ $title }}</h3>
                                <p>{{ $description }}</p>
                                <code>{{ $apiBase }}{{ $path }}</code>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="api-docs-section" id="kurallar">
                <div class="api-docs-section-title">
                    <span>04</span>
                    <div>
                        <h2>Kullanım kuralları</h2>
                        <p>Nozu API düşük trafikli kişisel, eğitim ve ücretsiz projeler için açık tutulur. Yoğun veya ticari kullanım için önceden izin gerekir.</p>
                    </div>
                </div>

                <div class="api-docs-grid">
                    <article class="api-docs-card">
                        <h3>Adil kullanım</h3>
                        <ul class="api-rule-list">
                            <li>Genel public limit: 60 istek/dakika/IP.</li>
                            <li>429 yanıtında Retry-After başlığına uyulmalıdır.</li>
                            <li>İstemciler sonuçları cache’lemelidir.</li>
                            <li>Tüm slug veya ID değerlerini otomatik taramak yasaktır.</li>
                            <li>Rate limit atlatmak için farklı IP kullanmak yasaktır.</li>
                        </ul>
                    </article>
                    <article class="api-docs-card">
                        <h3>Atıf ve ticari kullanım</h3>
                        <ul class="api-rule-list">
                            <li>Public uygulamalarda “Veriler Nozu tarafından sağlanmaktadır.” atfı nozu.me bağlantısıyla görünür olmalıdır.</li>
                            <li>Reklam gelirli, ücretli, abonelikli, kurumsal veya yüksek trafikli uygulamalar için yazılı izin gerekir.</li>
                            <li>Toplu veri aktarımı ve özel limit talepleri için destek@nozu.me ile iletişime geçilmelidir.</li>
                        </ul>
                    </article>
                    <article class="api-docs-card">
                        <h3>Yasaklı kullanımlar</h3>
                        <ul class="api-rule-list">
                            <li>API cevaplarını yeniden satmak yasaktır.</li>
                            <li>Nozu ile resmi bağlantı varmış gibi davranılamaz.</li>
                            <li>Korsan video, bölüm veya manga içerik dağıtımında kullanılamaz.</li>
                            <li>İzinsiz güvenlik testi, fuzzing veya yoğun otomatik tarama yapılamaz.</li>
                        </ul>
                    </article>
                    <article class="api-docs-card">
                        <h3>Sürümleme ve kaldırma</h3>
                        <p>Tüm public endpointler /api/v1 altında kalır. Geriye dönük uyumluluk korunmaya çalışılır; kaldırılması gereken alanlar için mümkün olduğunda geçiş süresi tanınır.</p>
                    </article>
                </div>
            </section>

            <section class="api-docs-section" id="kaynaklar">
                <div class="api-docs-section-title">
                    <span>05</span>
                    <div>
                        <h2>Kaynaklar</h2>
                        <p>OpenAPI çıktısı, cache önerileri ve destek kanalı.</p>
                    </div>
                </div>

                <div class="api-docs-grid">
                    <article class="api-docs-card">
                        <h3>OpenAPI</h3>
                        <p>Public OpenAPI çıktısı yalnızca dokümante edilen katalog endpointlerini içerir.</p>
                        <a class="button ghost" href="{{ $apiBase }}/openapi.json" target="_blank" rel="noopener">OpenAPI JSON</a>
                    </article>
                    <article class="api-docs-card">
                        <h3>HTTP cache</h3>
                        <p>Yanıtlarda ETag, Last-Modified ve Cache-Control başlıkları bulunabilir. Aynı içeriği sık tekrar çağırmak yerine istemci cache’i kullanılmalıdır.</p>
                        <div class="api-code">
                            <button type="button" class="api-copy">Kopyala</button>
                            <pre><code>ETag: "..."
Last-Modified: Fri, 31 Jul 2026 10:00:00 GMT
Cache-Control: public, max-age=60</code></pre>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </main>

    <script>
        document.querySelectorAll('.api-copy').forEach((button) => {
            button.addEventListener('click', async () => {
                const code = button.closest('.api-code')?.querySelector('code')?.innerText || '';
                if (!code) return;
                await navigator.clipboard.writeText(code);
                button.textContent = 'Kopyalandı';
                setTimeout(() => button.textContent = 'Kopyala', 1200);
            });
        });
    </script>
@endsection
