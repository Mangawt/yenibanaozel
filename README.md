# nozu.me

nozu.me, Türkçe anime ve manga keşif/veritabanı sitesidir. Laravel, database queue ve arka plan worker yapısıyla AniList kaynaklı içerikleri güvenli şekilde içe aktarır.

## Temel Komutlar

```bash
composer install
npm install
npm run build
php artisan migrate
php artisan storage:link
php artisan serve
```

## Ortam Ayarları

Production için önemli `.env` değerleri:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://nozu.me
QUEUE_CONNECTION=database
ADMIN_PASSWORD=Nasiptorun55.
```

Local geliştirmede `APP_URL=http://127.0.0.1:8000` kalabilir.

## Import Queue

Import işlemleri HTTP request içinde çalışmaz. Admin panelinden enqueue edilen kayıtlar `import_queue` tablosuna yazılır ve her kayıt için `ImportQueueJob` Laravel queue sistemine dispatch edilir.

Queue adı:

```text
imports
```

Worker:

```bash
php artisan queue:work database --queue=imports --sleep=2 --timeout=300 --max-jobs=500
```

Pending/running kayıtları güvenli şekilde tekrar dispatch etmek için:

```bash
php artisan nozume:import-queue
```

## Production Deploy

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

Supervisor örneği:

```text
deploy/supervisor/nozume-import-worker.conf
```

Kurulum:

```bash
sudo cp deploy/supervisor/nozume-import-worker.conf /etc/supervisor/conf.d/nozume-import-worker.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart nozume-import-worker
```

Worker `numprocs=1` çalışmalıdır.

## Detaylı Doküman

İşleyiş, deploy, queue, Supervisor ve sorun giderme notları:

```text
docs/NOZUME_ISLEYIS.md
```
