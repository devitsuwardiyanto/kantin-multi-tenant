# Pertemuan 06 — Quality Gate

Branch `feat/pertemuan-06-qr-session`, 2026-08-17. DB MariaDB `kantin_test`. (Pedoman Laravel Boost diadopsi.)

| Command | Exit | Ringkasan |
|---|---:|---|
| `php artisan test` | 0 | **77 passed** (182 assertions) — 8 baru (3 unit token + 5 feature QR/sesi) |
| `composer test` (pint+phpstan+test) | 0 | pint · phpstan 0 · 77 passed |
| `npm run build` | 0 | OK |
| `git diff --check` | 0 | bersih |

Demo live (server lokal):
```
GET /q/<token-valid>  -> 302 Location: /kantin/kantin-pusat + Set-Cookie: customer_session=…; httponly; samesite=lax
GET /q/tidak-ada      -> 404 (generik)
```
