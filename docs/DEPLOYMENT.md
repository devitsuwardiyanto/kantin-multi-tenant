# Deployment Checklist — Kantin Multi-Tenant

Panduan rilis produksi. `main` selalu = pertemuan terakhir yang LULUS (CI hijau).

## 1. Prasyarat runtime
- PHP **8.3** (ext: pdo_mysql, redis/phpredis, mbstring, openssl, bcmath)
- **MariaDB 11.4+** (transaksi, composite FK, generated column, CHECK)
- **Redis 7+** (session, cache, queue, cart)
- **Node 22** (build aset Vite)
- Server **Reverb** (WebSocket broadcasting) + worker **queue**

## 2. Variabel lingkungan (jangan commit)
```
APP_ENV=production
APP_KEY=<php artisan key:generate>
APP_DEBUG=false
APP_URL=https://<domain>

DB_CONNECTION=mysql
DB_HOST=... DB_PORT=3306 DB_DATABASE=... DB_USERNAME=... DB_PASSWORD=...

REDIS_CLIENT=phpredis
REDIS_HOST=... REDIS_PORT=6379 REDIS_PASSWORD=...

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=... REVERB_APP_KEY=... REVERB_APP_SECRET=...
REVERB_HOST=... REVERB_PORT=443 REVERB_SCHEME=https

QRIS_WEBHOOK_SECRET=<secret HMAC webhook pembayaran>
```

## 3. Langkah rilis
```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan storage:link
```
- Jalankan worker: `php artisan queue:work --queue=default`
- Jalankan WebSocket: `php artisan reverb:start`

## 4. Keamanan (sudah tertanam)
- Header keamanan global (`SecurityHeaders`): `nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, dll.
- Cookie sensitif HttpOnly + `Secure` di produksi (`SameSite=Lax`); `order_tracking` sengaja tak dienkripsi (token opaque hashed).
- Webhook pembayaran: verifikasi **signature HMAC atas raw body** + dedup + **rate limit** (`throttle:qris-webhook`); CSRF dikecualikan hanya untuk `webhooks/*`.
- Rate limit scan QR (`throttle:qr-scan`).
- `DB::prohibitDestructiveCommands` aktif di produksi; kebijakan **password kuat** (min 12, mixed, uncompromised) di produksi.
- Isolasi tenant berlapis: `TenantContext` (scoped) + global scope + policy + **composite FK** + generated-column UNIQUE.
- Uang = integer Rupiah; event & ledger **append-only**; koreksi via **reversal**. `CHECK` saldo non-negatif.
- **Jangan** `FLUSHDB` pada Redis bersama; key keranjang ter-scope sesi + TTL.

## 5. Pasca-rilis
- Verifikasi: login, scan QR demo, checkout, webhook sandbox, KDS realtime, rekonsiliasi `cocok`.
- Pantau: log worker/Reverb, `payment_events` (result), `ledger_entries`, `audit_logs`.
- Rollback: checkout tag pertemuan sebelumnya (rilis ber-tag `pertemuan-NN`).

## 6. Laravel Cloud
Alternatif tercepat: deploy via [Laravel Cloud](https://cloud.laravel.com/) (managed MariaDB + Redis + Reverb + worker). Set env di atas pada dashboard; build & migrate otomatis.
