# Nozu CMS V2 Dokümantasyon

Bu dosya Nozu CMS V2 için tek ana dokümantasyon kaynağıdır. Kurulum, geliştirme, production deploy, admin paneli, import queue, Smart Sync, otomatik çeviri, ücretsiz public API, bakım ve test notları burada tutulur.

## Hızlı Başlangıç

Local geliştirme kurulumu:

```bash
composer install
npm install
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
DB_QUEUE_RETRY_AFTER=360
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
- Çeviri sağlayıcı ayarları

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
deploy/supervisor/nozume-import-worker.conf
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

- `nozume-import-worker`
- `nozume-scanner-worker`

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

Modlar:

- `missing`: Mevcut içerikleri detay isteğine gitmeden atlar
- `full`: Mevcut ve yeni içerikleri kuyruğa alabilir
- `updates`: Mevcut içerikleri refresh kuyruğuna alabilir

## Nozu API

Public API dokümantasyonu:

```text
/api
```

Tüm `/api/v1/*` endpointleri ücretsiz ve anahtarsız kullanılabilir. API key, başvuru veya plan seçimi gerekmez.

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

## Çeviri

Çeviri ayarları admin panelinden yönetilir.

Aktif sağlayıcı `deepl`, `google`, `gemini` veya `none` olabilir. DeepL aktif ve anahtar kayıtlıysa import sırasında özetler otomatik Türkçeye çevrilir.

DeepL çağrılarında header tabanlı authentication kullanılır:

```http
Authorization: DeepL-Auth-Key {API_KEY}
```

Çeviri servis anahtarları loglanmaz.

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
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan nozume:import-queue
php artisan nozume:sync-resume
php artisan queue:restart
```

Scheduler cron:

```cron
* * * * * cd /var/www/nozu && php artisan schedule:run >> /dev/null 2>&1
```

Supervisor kontrolü:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart nozume-import-worker
sudo supervisorctl restart nozume-scanner-worker
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
