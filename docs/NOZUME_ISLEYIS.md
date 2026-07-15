# nozu.me İşleyiş ve Deploy Notları

Bu doküman nozu.me sitesinin genel çalışma mantığını, import queue sistemini ve sunucuya deploy ederken dikkat edilecek adımları özetler.

## Genel Yapı

nozu.me Laravel tabanlı bir anime ve manga veritabanıdır.

Ana bileşenler:

- Laravel uygulaması
- MySQL veya local geliştirme için SQLite
- Public site: ana sayfa, arama, anime/manga detayları, sanatçılar, stüdyolar, API dokümanı
- Admin paneli: `/adminasip`
- Import Queue sistemi
- Laravel Queue Worker
- DeeL/Google/Gemini destekli otomatik çeviri
- Public nozu.me API

## Önemli Ortam Ayarları

Production için `.env` dosyasında temel ayarlar:

```env
APP_NAME=nozu.me
APP_ENV=production
APP_DEBUG=false
APP_URL=https://nozu.me

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nozume
DB_USERNAME=...
DB_PASSWORD=...

QUEUE_CONNECTION=database

ADMIN_PASSWORD=Nasiptorun55.
```

Local geliştirmede `APP_URL=http://127.0.0.1:8000` kalabilir. Domain zorlaması sadece production ortamında yapılmalıdır.

## Admin Paneli

Admin panel adresi:

```text
/adminasip
```

Panelde:

- Tekil anime/manga arama ve ekleme
- Import Queue yönetimi
- Site ayarları
- Logo, favicon, site açıklaması
- Çeviri sağlayıcı ayarları

bulunur.

## Import Queue Mantığı

Import sistemi HTTP isteği içinde import yapmaz.

Akış:

1. Admin panelinden filtrelerle ID keşfi yapılır veya AniList linkleri/ID'leri toplu yapıştırılır.
2. Bulunan ID'ler `import_queue` tablosuna `pending` durumunda kaydedilir.
3. Her queue item için ayrı `ImportQueueJob` dispatch edilir.
4. Enqueue işlemi tek bir Laravel `Bus::batch` oluşturur.
5. `queue:work` arka planda job'ları işler.
6. Aynı anda yalnızca 1 worker çalıştırılır.
7. Job'lar `imports` queue adıyla database queue bağlantısında çalışır.
8. Her job yaklaşık 2 saniye aralıkla planlanır.
9. Bu hız yaklaşık 30 iş/dakika sınırına denk gelir.

Queue status değerleri:

- `pending`: İşlenmeyi bekliyor
- `running`: Şu an işleniyor
- `completed`: Başarıyla tamamlandı veya zaten veritabanında mevcut olduğu için tamamlandı sayıldı
- `skipped`: İşlenmeden atlandı
- `failed`: Hata aldı

## Job Davranışı

`ImportQueueJob` production queue kurallarına göre çalışır:

- `ShouldQueue` implement eder
- `Batchable` kullanır
- `WithoutOverlapping` middleware kullanır
- Aynı `source/type/external_id` aynı anda iki kez işlenemez
- `backoff()` değerleri: 30, 60, 120, 240 saniye
- `retryUntil()` 12 saatlik güvenli tekrar penceresi verir
- Timeout 300 saniyedir
- Job failed olduğunda Laravel `failed_jobs` tablosu kullanılır

AniList 429 rate limit yanıtı alınırsa:

- Queue item `failed` yapılmaz
- Queue item tekrar `pending` yapılır
- Job `release()` ile gecikmeli yeniden kuyruğa alınır
- Olay `storage/logs/import-*.log` altında kaydedilir

## Duplicate Kontrolü

Aynı içerik iki kez import edilmez.

Kontrol noktaları:

- Kuyruğa eklemeden önce `media.source_ids.anilist` kontrol edilir.
- Kuyrukta aynı `source/type/external_id` pending veya running ise tekrar eklenmez.
- Job çalışırken de tekrar kontrol yapılır.
- Eğer seri veritabanında zaten varsa API'den detay çekilmez; queue item doğrudan `completed` yapılır.
- Slug sonundaki AniList ID de ikinci bir güvenlik kontrolü olarak kullanılır.

## Queue Worker

Local geliştirmede worker başlatmak için:

```bash
php artisan queue:work database --queue=imports --sleep=2 --tries=1 --timeout=300
```

Pending kayıtları yeniden job olarak dispatch etmek için:

```bash
php artisan nozume:import-queue
```

Normal kullanımda admin panelinden enqueue yapınca job'lar otomatik dispatch edilir.

## Supervisor

Production ortamında worker sürekli çalışmalıdır.

Örnek Supervisor dosyası:

```text
deploy/supervisor/nozume-import-worker.conf
```

Sunucudaki gerçek path'e göre `command` ve `stdout_logfile` alanları güncellenmelidir.

Örnek komut:

```bash
sudo cp deploy/supervisor/nozume-import-worker.conf /etc/supervisor/conf.d/nozume-import-worker.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start nozume-import-worker
```

Worker sayısı `numprocs=1` kalmalıdır. Böylece aynı anda sadece 1 import çalışır.

Supervisor worker başlamadan önce `php artisan nozume:import-queue` çalıştırır. Böylece sunucu restart sonrası `running` durumda kalmış kayıtlar tekrar `pending` yapılır ve pending kayıtlar tekrar queue job olarak dispatch edilir.

## Deploy Adımları

Tipik deploy:

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan nozume:import-queue
php artisan queue:restart
```

Sonrasında Supervisor worker'ın çalıştığı kontrol edilir:

```bash
sudo supervisorctl status nozume-import-worker
```

## Çeviri Sistemi

Çeviri ayarları admin panelinden yönetilir.

Desteklenen sağlayıcılar:

- DeepL
- Google Translate
- Gemini
- Kapalı

DeepL aktif ve API anahtarı kayıtlıysa özetler DeepL ile Türkçeye çevrilir. Sağlayıcı kullanılamazsa sistem yedek çeviri mekanizmasına düşer. Çeviri kapalı seçilirse metin olduğu gibi saklanır.

## Görseller

Import sırasında görseller yerel storage alanına kaydedilir.

Genel path:

```text
storage/app/public/media/{type}/{external_id}/...
```

Public URL:

```text
/storage/media/...
```

## Public API

API adresi:

```text
/api
```

JSON endpointleri:

```text
GET /api/v1/search
GET /api/v1/anime/{slug}
GET /api/v1/manga/{slug}
```

Rate limit:

```text
30 istek / dakika
```

Public API çıktısında ham kaynak payload veya kaynak ID haritası gösterilmez.

## Admin Queue Ekranı

Import Queue sayfasında:

- Batch total jobs
- Batch pending
- Batch processed
- Batch failed
- Batch progress
- Pending
- Running
- Completed
- Skipped
- Failed
- Toplam
- İşlenen
- Kalan
- Şu an işlenen seri
- Son işlenen seri
- Yüzde
- Ortalama hız
- Tahmini bitiş süresi

gösterilir.

Bu metrikler sayfa yenilenmeden belirli aralıklarla güncellenir.

## Sorun Giderme

Worker çalışmıyorsa:

```bash
php artisan queue:failed
php artisan queue:restart
sudo supervisorctl restart nozume-import-worker
```

Pending kayıtlar job'a dönüşmediyse:

```bash
php artisan nozume:import-queue
```

Storage görselleri görünmüyorsa:

```bash
php artisan storage:link
```

Cache sorunlarında:

```bash
php artisan optimize:clear
```
