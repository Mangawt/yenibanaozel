# Nozu CMS V2 Dokümantasyon

Bu dosya Nozu CMS V2 için tek ana dokümantasyon kaynağıdır. Kurulum, geliştirme, production deploy, admin paneli, import queue, Smart Sync, otomatik çeviri, ücretsiz public API, bakım ve test notları burada tutulur.

## Hızlı Başlangıç

Local geliştirme kurulumu:

```bash
composer install
npm ci
npm run build
php artisan migrate
php artisan storage:link
php artisan nozu:sync-catalog
php artisan serve
```

Local geliştirmede `APP_URL=http://127.0.0.1:8000` kalabilir. Production için `APP_URL=https://nozu.me` kullanılır.

## Genel Yapı

nozu.me Laravel tabanlı bir anime ve manga veritabanıdır.

Ana bileşenler:

- Laravel uygulaması
- MySQL veya local geliştirme için SQLite
- Public site: ana sayfa, arama, anime/manga detayları, sanatçılar, stüdyolar, API dokümantasyonu
- Admin paneli: `/admin`
- Geriye dönük yönlendirme: `/adminasip` -> `/admin`
- Import Queue sistemi
- Smart Sync scanner sistemi
- Laravel database queue worker
- DeepL/Google/Gemini destekli otomatik çeviri
- Normalize katalog tabloları: people, characters, studios
- Ücretsiz public Nozu API
- Chrome eklentisi için Sanctum tabanlı kullanıcı API'si
- Opsiyonel Türkçe satın alma linkleri

## Ortam Ayarları

Production için `.env` dosyasında temel ayarlar:

```env
APP_NAME=nozu.me
APP_ENV=production
APP_DEBUG=false
APP_URL=https://nozu.me
NOZU_PUBLIC_URL=https://nozu.me
NOZU_PUBLIC_API_URL=https://nozu.me/api/v1

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nozume
DB_USERNAME=...
DB_PASSWORD=...

QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=900

NOZU_EXTENSION_ORIGIN=chrome-extension://EXTENSION_ID
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,nozu.me
```

Local geliştirmede `APP_URL=http://127.0.0.1:8000` kalabilir. API dokümanı ve OpenAPI çıktısı ise `NOZU_PUBLIC_URL` / `NOZU_PUBLIC_API_URL` değerlerinden üretilir; bu yüzden local çalışırken bile public API örnekleri canlı domaini gösterir. Production ortamında `APP_URL`, `NOZU_PUBLIC_URL` ve `NOZU_PUBLIC_API_URL` canlı domainle ayarlanmalıdır.

## Admin Paneli

Admin panel adresi:

```text
/admin
```

İlk super admin kullanıcıyı oluşturmak için:

```bash
php artisan nozu:create-admin
```

Geçerli admin rolleri:

- `super_admin`
- `admin`
- `moderator`
- `editor`
- `translator`
- `viewer`

`viewer` salt okunur roldür. Admin panelinde izin verilen sayfaları görüntüleyebilir; içerik oluşturamaz, düzenleyemez, silemez, queue retry/silme işlemi yapamaz, Smart Sync başlatıp durduramaz ve ayar değiştiremez.

Panelde:

- Tekil anime/manga arama ve ekleme
- Import Queue yönetimi
- Smart Sync yönetimi
- Sistem durumu ve log izleme
- Site ayarları
- Logo, favicon, site açıklaması
- Chrome eklentisi footer linki
- Çeviri sağlayıcı ayarları
- Anime/manga düzenleme ekranında Türkçe satın alma linki

bulunur.

## Import Queue Mantığı

Import sistemi HTTP isteği içinde import yapmaz.

Akış:

1. Admin panelinden filtrelerle kayıt keşfi yapılır veya kaynak linkleri/ID'leri toplu yapıştırılır.
2. Bulunan ID'ler `import_queue` tablosuna `pending` durumunda kaydedilir.
3. Her queue item için ayrı `ImportQueueJob` dispatch edilir.
4. Enqueue işlemi tek bir Laravel `Bus::batch` oluşturur.
5. `queue:work` arka planda job'ları işler.
6. Aynı anda yalnızca 1 import worker çalıştırılır.
7. Job'lar `imports` queue adıyla database queue bağlantısında çalışır.

`queue:work --sleep=2` joblar arasında iki saniye bekletmez. Bu seçenek yalnızca kuyruk boşken worker'ın tekrar kontrol süresidir. Harici API hız kontrolü job/service katmanındaki retry, delay ve rate-limit davranışıyla yapılır.

Queue status değerleri:

- `pending`: Sırada veya geçici retry bekliyor
- `running`: Şu an işleniyor
- `completed`: Başarıyla import edildi veya güncellendi
- `skipped`: Zaten mevcut, işleme gerek yok veya filtre dışı
- `failed`: Kalıcı hata aldı

## Job Davranışı

`ImportQueueJob` production queue kurallarına göre çalışır:

- `ShouldQueue` implement eder
- Queue connection: `database`
- Queue name: `imports`
- `WithoutOverlapping` middleware kullanır
- Aynı external ID aynı anda iki kez işlenmez
- `tries`, `timeout`, `backoff()` ve `retryUntil()` tanımlıdır
- 429 rate limit durumunda job kalıcı failed olmaz, gecikmeyle tekrar kuyruğa alınır
- Duplicate kontrolü job çalışırken tekrar yapılır
- İçerik zaten varsa normal importta detay tekrar çekilmez, item `skipped` olur
- Smart Sync `full` ve `updates` modunda `force_refresh` ile mevcut içerikleri yenileme kuyruğuna alabilir

## Duplicate Kontrol Sırası

Queue oluşturulurken ve job çalışırken:

1. `media.source_ids`
2. Slug sonundaki harici ID
3. Queue içinde `pending`
4. Queue içinde `running`

kontrol edilir.

## Dashboard Verileri

Import Queue ekranı otomatik yenilenir ve şu verileri gösterir:

- Toplam
- Pending
- Running
- Completed
- Failed
- Skipped
- Şu an işlenen seri
- İşlenen / Toplam
- Yüzde
- Dakikadaki işlem sayısı
- ETA
- Son işlenen seri
- Son batch bilgisi

## Supervisor

Örnek Supervisor dosyası:

```text
deploy/supervisor/nozu-workers.conf
```

Temel ayarlar:

```ini
numprocs=1
autorestart=true
stopwaitsecs=360
```

Import worker başlamadan önce:

```bash
php artisan nozume:import-queue
```

çalıştırılır. Böylece sunucu restart sonrası `running` durumda kalmış kayıtlar tekrar `pending` yapılır ve pending kayıtlar tekrar queue job olarak dispatch edilir.

Scanner worker başlamadan önce:

```bash
php artisan nozume:sync-resume
```

çalıştırılır. Böylece yarım kalan Smart Sync taramaları güvenli şekilde devam eder.

Supervisor örneğinde iki program hazırdır:

- `nozu-import`
- `nozu-scanner`
- `nozu-images`

## Smart Sync

Smart Sync ekranı:

```text
/admin/sync
```

Mimari:

```text
Scanner -> Difference/Queue Decision -> ImportQueueService -> ImportQueueJob -> Media
```

Scanner doğrudan Media oluşturmaz ve import metodunu doğrudan çağırmaz. Sadece keşfedilen ID'leri mevcut queue sistemine ekler.

Sync state bilgileri `sync_states` tablosunda saklanır. Böylece scanner restart sonrası kaldığı sayfadan devam edebilir.

Full katalog taramalarında yıl/format ilerlemesi ayrıca `sync_partition_states` tablosunda kalıcı tutulur. Her partition için yıl, format, durum, aktif sayfa, son başarılı sayfa, son sayfa, işlenen/yeni/güncellenen/atlanan/hata sayaçları ve hata mesajı saklanır.

Partition durumları:

- `pending`: BEKLİYOR
- `running`: DEVAM
- `completed`: OK
- `waiting_rate_limit`: BEKLEME
- `paused`: DURAKLATILDI
- `failed`: HATA
- `skipped`: ATLANDI
- `stopped`: DURDURULDU

Admin tablosu:

- Yılları satır, formatları sütun olarak gösterir.
- Anime sütunları: `TV`, `MOVIE`, `OVA`, `ONA`, `SPECIAL`, `MUSIC`, `TV_SHORT`.
- Manga sütunları: `MANGA`, `NOVEL`, `ONE_SHOT`.
- Durum filtreleri: Tümü, Devam eden, Tamamlanan, Hatalı, Bekleyen, Bekleme.
- Özet alanı tamamlanan partition, devam eden, bekleyen, hatalı ve katalog ilerleme yüzdesini gösterir.
- Çok geniş kataloglarda tablo ilk yılları ve aktif yılı gösterir; tüm toplamlar yine bütün partition kayıtlarından hesaplanır.

Scanner rate limit davranışı:

- Her GraphQL çağrısı 1 gerçek HTTP isteği sayılır.
- Dönen kayıt sayısı rate limit hesabına dahil edilmez.
- Bir GraphQL isteği 50 kayıt döndürürse 1 istek sayılır.
- `requests_in_window`, `window_started_at`, `current_page`, `last_successful_page`, `next_run_at` ve `status` `sync_states` içinde saklanır.
- 30 gerçek HTTP isteğine ulaşıldığında state kaydedilir ve sonraki scanner job 60 saniye delay ile dispatch edilir.
- Bekleme `sleep()` ile yapılmaz; worker bloklanmaz.
- 429 geldiğinde `Retry-After` headerı okunur.
- `Retry-After` yoksa en az 60 saniye beklenir.
- Scanner kalıcı failed durumuna düşmez; `waiting_rate_limit` ile kaldığı sayfadan devam eder.
- Rate limit sırasında aktif partition `waiting_rate_limit` görünür ve aynı yıl/format/sayfadan devam eder.
- Cache lock alınamazsa `WithoutOverlapping` job'u düşürmez; `releaseAfter(60)` ile tekrar kuyruğa bırakır. Lock en fazla `expireAfter(300)` saniye tutulur.

Modlar:

- `missing`: Mevcut içerikleri detay isteğine gitmeden atlar
- `full`: Mevcut ve yeni içerikleri kuyruğa alabilir
- `updates`: Mevcut içerikleri refresh kuyruğuna alabilir

Tarama kapsamları:

- `standard`: Seçilen filtre ve sayfa aralığı için normal tarama yapar.
- `full_catalog`: Yılları güncel yıldan `end_year` değerine doğru geriye sarar, seçili formatlara böler ve her yıl/format kombinasyonunda en fazla 100 sayfa gezer.

Full katalog taramasında anime ve manga ayrı tipler olarak çalışır. Manga taramalarında yıl filtresi sezon yılı yerine başlangıç tarihi aralığıyla uygulanır. Scanner doğrudan medya oluşturmaz; bulduğu ID'leri her zaman Import Queue sistemine aktarır.

0 sonuç dönen yıl/format partitionları da başarılı tamamlanmış sayılır. Örneğin `2026 / TV_SHORT` ilk sayfada 0 kayıt döndürürse partition `completed` olur, state sonraki yıl/format konumuna ilerler ve yeni `AniListScannerJob` dispatch edilir. Böylece sync state `running` kalıp scanner queue'nun boş kaldığı normal bir akış oluşmaz.

Admin Smart Sync ekranında full katalog taramaları için ayrıntılı yıl/format tablosu gösterilir. Tablo yatay kaydırmalıdır, durum filtresi destekler ve OK/DEVAM/BEKLİYOR/BEKLEME/HATA durumlarını partition tablosundan okur.

Güncel tutma eşikleri:

- `RELEASING`: 6 saatte bir yenilenebilir.
- `NOT_YET_RELEASED`: 12 saatte bir yenilenebilir.
- `HIATUS`: 3 günde bir yenilenebilir.
- `FINISHED`: 30 günde bir yenilenebilir.
- `CANCELLED`: 90 günde bir yenilenebilir.

Bu eşikler `last_external_sync_at` alanına göre hesaplanır. Başarılı import veya refresh sonunda bu alan güncellenir. `prioritize_active` kapatılırsa genel `update_stale_after_days` değeri kullanılır.

Planlı Smart Sync görevleri:

- Aktif anime: 6 saatte bir
- Aktif manga: 6 saatte bir
- Son yıllar anime/manga: günde bir
- Son 10 yıl anime/manga: haftada bir
- Tüm katalog anime/manga: ayda bir

Scheduler şu komutları çalıştırır:

```bash
php artisan nozu:smart-sync-schedule active anime
php artisan nozu:smart-sync-schedule active manga
php artisan nozu:smart-sync-schedule recent anime
php artisan nozu:smart-sync-schedule recent manga
php artisan nozu:smart-sync-schedule decade anime
php artisan nozu:smart-sync-schedule decade manga
php artisan nozu:smart-sync-schedule monthly anime
php artisan nozu:smart-sync-schedule monthly manga
```

Planlı taramalar `withoutOverlapping` ve duplicate sync kontrolüyle korunur; aynı tür, mod ve kapsam için çalışan bir scanner varken ikincisi başlatılmaz.

## Nozu API

Public API dokümantasyonu:

```text
/api
```

Public katalog endpointleri ücretsiz ve anahtarsızdır. Kullanıcıya özel auth ve `/me` endpointleri Sanctum Bearer token gerektirir.

Public API rate limit:

```text
60 istek/dakika / IP
```

Limit aşıldığında `429` response döner ve yanıtta `X-RateLimit-Limit`, `X-RateLimit-Remaining` ve `Retry-After` headerları bulunur.

Örnek endpointler:

```text
/api/v1/search
/api/v1/discover
/api/v1/latest
/api/v1/popular
/api/v1/trending
/api/v1/random
/api/v1/anime/{slug}
/api/v1/manga/{slug}
/api/v1/recommendations/{slug}
/api/v1/media?ids=20,30,41
/api/v1/media/batch
/api/v1/studios
/api/v1/people
/api/v1/openapi.json
```

Başarılı response formatı:

```json
{
  "success": true,
  "data": [],
  "meta": {},
  "links": {}
}
```

Hata response formatı:

```json
{
  "success": false,
  "message": "...",
  "errors": []
}
```

Liste endpointlerinde pagination bilgileri `meta` ve `links` içinde döner. `fields` parametresi minimal response, `include` parametresi ilişkili veri seçimi için kullanılabilir.

API response'larında HTTP cache için `ETag`, `Last-Modified` ve `Cache-Control` headerları üretilir.

### Chrome Eklentisi Kullanıcı API'si

Chrome eklentisinin nozu.me hesabıyla güvenli giriş yapabilmesi için Sanctum tabanlı kullanıcı API'si vardır. Bu API mevcut public endpointleri bozmaz ve yine `/api/v1` altında çalışır.

Kimlik doğrulama:

- Laravel Sanctum kullanılır.
- Tokenlar veritabanında düz metin saklanmaz.
- Kullanıcı tokenı `Authorization: Bearer {TOKEN}` headerı ile gönderilir.
- Login endpointinde IP ve e-posta hash'i + IP bazlı rate limit uygulanır.
- Hatalı girişlerde genel mesaj döner; parola veya token loglanmaz.
- Token ve kullanıcıya özel cevaplarda HTTP cache kapalıdır: `Cache-Control: no-store, private`.
- Token süresi 30 gündür. `.env` tarafında `SANCTUM_TOKEN_EXPIRATION=43200` dakika olarak tutulur.
- Token yalnızca başarılı login sırasında bir kez plain text döner; daha sonra veritabanında hashli saklanır.
- Chrome extension tokenlarına yalnızca `extension:read` ve `extension:list-write` ability değerleri verilir.

Ability kuralları:

- `GET /api/v1/me`: `extension:read`
- `GET /api/v1/me/list`: `extension:read`
- `GET /api/v1/media/{media}/my-list`: `extension:read`
- `POST /api/v1/me/list`: `extension:list-write`
- `DELETE /api/v1/me/list/{media}/{status}`: `extension:list-write`
- Eski `DELETE /api/v1/me/list/{media}` endpointi geriye uyumluluk için durur; yalnızca favori dışı ana liste durumunu siler ve favoriyi silmez.

Rate limit değerleri:

- Login: IP başına dakikada 10
- Login: e-posta hash'i + IP başına dakikada 5
- Authenticated okuma: kullanıcı başına dakikada 120
- Authenticated okuma: IP başına dakikada 180
- Liste yazma: kullanıcı başına dakikada 30
- Liste silme: kullanıcı başına dakikada 10

Chrome extension CORS ayarı `.env` üzerinden dar kapsamlı yapılır:

```env
NOZU_EXTENSION_ORIGIN=chrome-extension://EXTENSION_ID
```

Tüm originler `*` ile açılmaz. Gerçek Chrome extension ID production `.env` dosyasına yazılmalıdır.

Endpointler:

```text
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
GET    /api/v1/me
GET    /api/v1/me/list
POST   /api/v1/me/list
DELETE /api/v1/me/list/{media}
DELETE /api/v1/me/list/{media}/{status}
GET    /api/v1/media/{media}/my-list
```

Login örneği:

```json
{
  "email": "kullanici@example.com",
  "password": "sifre",
  "device_name": "Nozu Chrome Extension"
}
```

Başarılı login cevabı:

```json
{
  "success": true,
  "data": {
    "token": "...",
    "user": {
      "id": 1,
      "name": "Nozu User",
      "avatar": null
    }
  },
  "meta": {},
  "links": {}
}
```

Liste sorgusu parametreleri:

- `type`: `anime` veya `manga`
- `status`: `watching`, `reading`, `completed`, `paused`, `dropped`, `planned`, `favorite`
- `page`
- `per_page`

Liste oluşturma/güncelleme örneği:

```json
{
  "media_id": 123,
  "status": "watching",
  "progress": 3,
  "score": 8
}
```

Durum uyumluluğu:

- Anime için ana ilerleme durumu: `watching`
- Manga için ana ilerleme durumu: `reading`
- Ortak durumlar: `completed`, `paused`, `dropped`, `planned`, `favorite`
- `progress` negatif olamaz.
- `score` 0-10 aralığında olmalıdır.
- Aynı kullanıcı ve aynı medya için aynı status varsa kayıt güncellenir.
- Aynı kullanıcı ve aynı medya için `favorite` ile ana izleme/okuma durumu ayrı satırlar olarak birlikte tutulabilir.
- `favorite` eklemek `watching`, `reading`, `completed`, `paused`, `dropped` veya `planned` kaydını silmez.
- Ana liste durumu değişirken `favorite` korunur; yalnızca önceki favori dışı ana durum temizlenir.
- Favori dışındaki liste durumu değiştirildiğinde aynı medya için önceki favori dışı durum temizlenir.
- API request içinde `user_id` veya `owner_id` kabul edilmez.
- Liste okuma, yazma ve silme işlemlerinde kullanıcı her zaman Bearer token üzerinden belirlenir.

Status bazlı silme:

```http
DELETE /api/v1/me/list/123/favorite
DELETE /api/v1/me/list/123/watching
```

Bu endpoint yalnızca giriş yapan kullanıcının ilgili `media + status` kaydını siler. Kayıt yoksa idempotent şekilde `200` döner ve `deleted: false` verir.

`my-list` response örneği:

```json
{
  "success": true,
  "data": {
    "status": "watching",
    "progress": 10,
    "score": 8,
    "is_favorite": true
  },
  "meta": {},
  "links": {}
}
```

Kullanıcının sadece favorisi varsa `status`, `progress` ve `score` `null`, `is_favorite` ise `true` döner. Kullanıcıya ait hiçbir kayıt yoksa `data: null` döner.

Chrome fetch örneği:

```js
const response = await fetch('https://nozu.me/api/v1/me/list', {
  method: 'POST',
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`
  },
  body: JSON.stringify({
    media_id: 123,
    status: 'watching',
    progress: 3,
    score: 8
  })
});
```

## Çeviri

Çeviri ayarları admin panelinden yönetilir.

Aktif sağlayıcı `azure`, `deepl`, `google`, `gemini` veya `none` olabilir. Import sırasında özetler otomatik Türkçeye çevrilir.

Çeviri sistemi sıralı sağlayıcı zinciriyle çalışır. Bir sağlayıcı kota, `429`, bağlantı veya servis hatası verirse sıradaki sağlayıcı denenir. Varsayılan önerilen sıra:

```text
gemini,google,azure
```

Bu sıra admin panelindeki `Çeviri sırası` alanından değiştirilebilir. Zincirde yer alan sağlayıcının kullanılabilmesi için ilgili `aktif` checkbox'ı işaretli ve API anahtarı/region gibi zorunlu alanları dolu olmalıdır.

Önerilen ayar:

- Aktif sağlayıcı: `gemini`
- Çeviri sırası: `gemini,google,azure`
- Gemini aktif: açık
- Google Translate aktif: açık
- Azure Translator aktif: açık

Gemini Flash için anime, manga, manhwa ve light novel özetlerine özel sistem talimatı kullanılır. Kurallar bilgi eklememeyi/çıkarmamayı, HTML etiketlerini ve paragraf yapısını korumayı, özel isimleri değiştirmemeyi ve terim sözlüğünü bağlama uygun uygulamayı zorunlu kılar.

DeepL çağrılarında header tabanlı authentication kullanılır:

```http
Authorization: DeepL-Auth-Key {API_KEY}
```

Çeviri servis anahtarları loglanmaz. Çeviri öncesinde HTML etiketleri silinmez; destekleyen sağlayıcılarda HTML formatı korunarak gönderilir.

## Görsel Depolama ve Bunny CDN

Nozu görsel cache sistemi iki katmanlı çalışır:

- Yerel fallback: `storage/app/public/media-cache`
- Eski seri bazlı görseller: `storage/app/public/media`
- Opsiyonel CDN katmanı: Bunny Storage + Bunny CDN

Eski görseller taşınmaz. `storage/app/public/media`, mevcut `storage/app/public/media-cache` dosyaları, eski `/storage/media/` URL'leri ve veritabanındaki eski görsel alanları toplu migration ile değiştirilmez. Bunny entegrasyonu yalnızca entegrasyon aktif edildikten sonra `ExternalMediaService::cacheImage()` / `localizeImage()` üzerinden işlenen yeni görseller için devreye girer.

Production `.env` ayarları:

```env
BUNNY_ENABLED=true
BUNNY_STORAGE_ZONE=nozu-media
BUNNY_STORAGE_KEY=SUNUCUDA_ELLE_EKLE
BUNNY_STORAGE_ENDPOINT=https://storage.bunnycdn.com
BUNNY_CDN_URL=https://nozu-media.b-cdn.net
```

`BUNNY_STORAGE_KEY` değeri kaynak koda, teste, loga, dokümana veya `.env.example` içine yazılmaz. Anahtar yalnızca production sunucusundaki `.env` dosyasına elle eklenir.

Bunny kapalıyken:

- Mevcut yerel `media-cache` davranışı aynen çalışır.
- Yerel dosya varsa ve 512 byte veya daha büyükse `/storage/media-cache/...` URL'si döner.
- Yeni dosya indirildikten sonra yerel cache'e atomik geçici dosya + move akışıyla yazılır.

Bunny açıkken:

1. Kaynak görsel URL'si indirilir.
2. HTTP status başarılı olmalıdır.
3. `Content-Type` `image/` ile başlamalıdır.
4. İçerik en az 512 byte olmalıdır.
5. Mevcut deterministik path korunur: `media-cache/{kategori}/{hash-prefix}/{identityHash-urlHash}.{ext}`.
6. Doğrulanan içerik Bunny Storage'a HTTP `PUT` ile yüklenir.
7. Bunny başarılıysa veritabanına `{BUNNY_CDN_URL}/{PATH}` formatında CDN URL'si kaydedilir.
8. Bunny başarısızsa import veya queue job başarısız olmaz; mevcut yerel `media-cache` fallback akışı çalışır.

Bunny upload URL formatı:

```text
{BUNNY_STORAGE_ENDPOINT}/{BUNNY_STORAGE_ZONE}/{PATH}
```

Path segmentleri URL encode edilir, slash yapısı korunur. Upload headerları:

```http
AccessKey: BUNNY_STORAGE_KEY
Content-Type: image/*
```

Bunny başarısız olduğunda loglarda yalnızca şu bilgiler tutulur:

- `storage_path`
- HTTP status
- response body'nin en fazla ilk 500 karakteri
- exception mesajı

Loglarda `BUNNY_STORAGE_KEY`, `AccessKey` headerı, tam config içeriği veya `.env` içeriği tutulmaz.

Yan görsel işi `CacheMediaImagesJob` mevcut güvenli fallback davranışını korur. Örneğin Bunny veya yerel indirme başarısız olursa mevcut kayıt null yapılmaz:

```php
$item['image'] = $external->localizeImage(...)
    ?? ($item['image'] ?? null);
```

## Site Tasarımı ve İçerik Yönetimi

Public arayüz karanlık tema varsayılan olacak şekilde tasarlanmıştır. Kullanıcı tema seçimleri `localStorage` içinde saklanır ve üç mod desteklenir:

- Karanlık tema
- Aydınlık tema
- Sistem temasını kullan

Mobil arayüzde ana menü küçük ekranlarda açılır menüye dönüşür. Arama alanı, profil menüsü ve tema butonu mobilde ayrı satır/hizalama kurallarıyla taşma yapmayacak şekilde düzenlenmiştir. Anime/manga detay sekmeleri mobilde yatay kaydırılabilir çalışır.

Ana sayfa sliderı modern geniş görsel yapısıyla çalışır. Slider görselleri medya `banner_image` alanından, yoksa `cover_image` alanından beslenir. Kart gridleri desktopta 6 seri gösterecek, mobilde ise taşmadan 2 kolon kullanılacak şekilde ayarlanmıştır.

Favori anime ve favori manga alanlarında kartlar küçük grid yapısıyla gösterilir. Kartlar üst üste binmez; fazla favori olduğunda profil sayfasındaki `Tümünü gör` kartı kullanılır.

Türkçe satın alma linki:

- `media.turkish_purchase_url` alanında saklanır.
- Admin panelindeki anime/manga düzenleme ekranından girilir.
- Public detay sayfasında link doluysa alışveriş ikonlu `Türkçe satın al` butonu görünür.
- Link boşsa kullanıcı tarafında satın alma butonu gösterilmez.

Chrome eklentisi footer kutusu:

- Admin ayarlarındaki `chrome_extension_url` alanından yönetilir.
- Link girilmişse footer açıklamasının altında Chrome ikonlu kutu olarak görünür.
- Link girilmemişse pasif `Chrome eklentisi yakında` kutusu görünür.

Admin Sistem Durumu ekranı:

- `/admin/status` adresindedir.
- Queue, failed jobs, batch ve son logları gösterir.
- Log dosyası okunamazsa veya bozuksa sayfa 500 hatasına düşmez; ilgili log alanı boş gösterilir.

## Katalog Senkronizasyonu

Yeni import edilen her medya kaydı `people`, `characters`, `studios` ve bağlantı tablolarına otomatik senkronize edilir.

Mevcut eski media kayıtlarını normalize tablolara doldurmak için ilk kurulumda veya ilgili migration sonrası manuel olarak:

```bash
php artisan nozu:sync-catalog
```

çalıştırılır. Büyük veritabanlarında her deploy sırasında zorunlu çalıştırılmamalıdır.

## Production Deploy Sırası

Gerçek proje dizini:

```text
/var/www/nozu
```

Deploy:

```bash
cd /var/www/nozu
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod 775 {} \;
find storage bootstrap/cache -type f -exec chmod 664 {} \;
composer install --no-dev --optimize-autoloader
npm ci
npm run build
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan storage:link
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache
sudo -u www-data php artisan nozume:import-queue
# Smart Sync kaldığı yerden devam ettirilecekse çalıştırılır:
# sudo -u www-data php artisan nozume:sync-resume
sudo -u www-data php artisan queue:restart
```

Laravel cache komutlarından sonra `storage` ve `bootstrap/cache` sahipliği tekrar `www-data` yapılmalıdır. Bu, cache lock ve compiled view dosyalarında izin kaynaklı worker hatalarını önler.

Scheduler cron:

```cron
* * * * * cd /var/www/nozu && php artisan schedule:run >> /dev/null 2>&1
```

Bu sunucuda cron servis adı:

```bash
systemctl status cron
```

Supervisor kontrolü:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart nozu-import
sudo supervisorctl restart nozu-scanner
sudo supervisorctl restart nozu-images
sudo supervisorctl status
```

## Test

```bash
php artisan test
```

Test kapsamı şunları içerir:

- Anahtarsız istek başarılı döner
- Standart JSON response korunur
- Pagination `meta` alanı döner
- `fields` ve `include` parametreleri çalışır
- Çoklu kayıt lookup anahtarsız kullanılabilir
- Public API 60 istek/dakika/IP limitini uygular
- Guest ve normal user admin paneline erişemez
- Admin erişebilir
- Viewer admin panelini görüntüler ama yazma işlemi yapamaz
- Admin login rate limit çalışır
- Logout session invalidate eder
- DeepL translate ve usage istekleri header authentication kullanır
- DeepL Free/Pro endpoint seçimi doğrulanır
- Legacy `auth_key` kullanımı yoktur
- Manga linkleri `manga` olarak queue'ya girer
- Manga import GraphQL `MANGA` type gönderir
- Null manga alanları exception üretmez
- Başarılı import `completed` olur
- Mevcut kayıt `skipped` olur
- Retry sonrası `error_message` temizlenir
- Duplicate queue kaydı eklenmez
- Geçici import hatası pending/retry durumunda kalır
- Kalıcı import hatası `failed` olur
- Running kayıtlar güvenli olmayan şekilde silinemez
- Completed cleanup çalışır
- Smart Sync eksik içeriği queue'ya ekler
- Güncel içerik `skipped` olur
- Eski içerik refresh queue'ya alınır
- İki scanner aynı anda başlayamaz
- Scanner doğrudan Media oluşturmaz
- Resume kaldığı sayfadan devam eder
- 29 gerçek HTTP isteğinde devam eder
- 30. gerçek HTTP isteğinde sonraki scanner job 60 saniye delay edilir
- 429 durumunda `Retry-After` değerine uyulur
- Aktif yayınlanan içerik 6 saat sonra refresh queue'ya alınır
- Tamamlanmış içerik 30 gün dolmadan refresh queue'ya alınmaz
- Full katalogda 0 sonuç dönen partition `completed` olur
- Boş partition sonrası scanner sıradaki yıl/format için yeni job dispatch eder
- Standard boş tarama `completed` olur ve yeni scanner job oluşturmaz
- Rate limit sırasında aktif partition `waiting_rate_limit` olur
- Admin Smart Sync ekranı partition tablosunu OK/DEVAM/Sayfa bilgisiyle render eder
- Bunny kapalıyken görsel upload isteği yapılmaz
- Bunny ayarları eksikken upload yapılmaz
- Bunny Storage HTTP PUT upload, `AccessKey` ve `Content-Type` headerları doğrulanır
- Bunny path segmentleri encode edilir, slash yapısı korunur
- Bunny 4xx/5xx veya exception durumunda `null` döner ve API anahtarı loglanmaz
- Bunny başarısız olduğunda `ExternalMediaService` yerel `media-cache` fallback kullanır
- Bunny kapalıyken mevcut yerel görsel cache davranışı bozulmaz
- Chrome eklentisi API login başarılı token döndürür
- Hatalı Chrome API login genel güvenli hata mesajı döndürür
- Token olmadan `/api/v1/me` erişimi 401 döner
- Kullanıcı kendi anime/manga liste kaydını oluşturabilir
- Kullanıcı kendi liste kaydını güncelleyebilir
- Kullanıcı başka kullanıcının liste durumunu göremez
- Logout sonrası mevcut Sanctum tokenı geçersiz olur
- Chrome API validation hataları standart JSON formatıyla döner
- Admin Sistem Durumu sayfası admin kullanıcıyla 500 vermeden açılır
