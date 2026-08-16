# Kantin Multi-Tenant

Aplikasi kantin multi-tenant (modular monolith) yang dibangun kumulatif selama 14 pertemuan.
Stack: **Laravel 13** · **Livewire 4** (starter kit) · **Reverb** · **MariaDB** · **Redis**.

> Basis desain: `references/SRS_..._ISO29148 v2.docx` dan modul praktikum v7 (lihat `references/`).
> Riwayat & pembekuan tiap pertemuan: annotated tag `pertemuan-01` … `pertemuan-14` + GitHub Release.
> Keputusan arsitektur: `docs/adr/`. Status & ketertelusuran: `docs/`.

## Requirements

| Tool | Versi minimum | Diuji pada |
|---|---|---|
| PHP | 8.3+ (ext: pdo_mysql, redis, mbstring, openssl, ctype, curl, fileinfo, xml, tokenizer, bcmath) | 8.3.20 |
| Composer | 2.x | 2.7.1 |
| Node / npm | 22 / 10 | 22.14.0 / 10.9.2 |
| MariaDB | 10.6+ | 11.4.5 |
| Redis | 7.x | 7.2.6 |
| Git | 2.x | 2.50.1 |

## Setup (instalasi bersih)

```bash
git clone git@github.com:devitsuwardiyanto/kantin-multi-tenant.git
cd kantin-multi-tenant

cp .env.example .env
# Edit .env: isi DB_DATABASE/DB_USERNAME/DB_PASSWORD (MariaDB) dan REVERB_APP_* (nilai lokal).
# Default proyek: DB_CONNECTION=mysql, DB_HOST=127.0.0.1, DB_PORT=3306, DB_DATABASE=kantin,
# SESSION_DRIVER/CACHE_STORE/QUEUE_CONNECTION=redis, REDIS_PORT=6379, APP_TIMEZONE=UTC, APP_LOCALE=id.

composer install
php artisan key:generate
npm install
npm run build

php artisan migrate            # membuat tabel pada database dev (kantin)
```

### Database pengujian terpisah

Test berjalan di MariaDB pada database **`kantin_test`** (lihat `phpunit.xml`), terpisah dari dev.
Buat sekali (butuh admin DB) lalu beri akses ke user aplikasi:

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS kantin_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON kantin_test.* TO 'kantin'@'localhost'; FLUSH PRIVILEGES;"
```

`.env.testing` (tidak di-commit) menunjuk `DB_DATABASE=kantin_test` untuk perintah `--env=testing`.

## Menjalankan (dev)

```bash
composer run dev     # HTTP server + queue worker + Vite (satu proses)
# Reverb (WebSocket) di terminal terpisah bila diperlukan:
php artisan reverb:start
```

Buka http://localhost:8000 — halaman login/registrasi (starter kit Livewire) siap dipakai.

## Test & quality gate

```bash
php artisan test                                   # 33 test (feature+unit) → kantin_test
composer test                                      # config:clear + pint + phpstan + test (perintah CI)
./vendor/bin/pint --test                           # format check
npm run build                                      # asset build
composer validate --strict && composer audit       # dependency
```

CI (`.github/workflows/tests.yml`) menjalankan gate yang sama pada PHP 8.3 + Node 22 dengan service
MariaDB 11.4 + Redis 7 terisolasi. Merge ke `main` melalui pull request; required check: **`ci`**.

## Troubleshooting cepat

| Gejala | Penyebab | Perbaikan |
|---|---|---|
| `could not find driver` | ext `pdo_mysql` nonaktif | aktifkan lalu restart PHP; `php -m \| grep pdo_mysql` |
| `Access denied ... kantin_test` | user app belum punya akses DB test | jalankan GRANT di atas (perlu admin DB) |
| `Failed to create broadcaster "reverb"` | `REVERB_APP_*` kosong | isi `REVERB_APP_ID/KEY/SECRET` di `.env` |
| Vite manifest not found | asset belum dibangun | `npm install && npm run build` |
| `Rolldown failed to resolve "laravel-echo"` | dep echo belum terpasang | `npm install laravel-echo pusher-js` |

## Struktur

```
app/ bootstrap/ config/ database/ public/ resources/ routes/ tests/   # aplikasi Laravel
references/   # artefak sumber (SRS, ERD, mockup, modul) — jangan diedit
docs/         # matrix, status, RTM, revision log, ADR
evidence/     # bukti per pertemuan (evidence/pertemuan-NN/)
```
