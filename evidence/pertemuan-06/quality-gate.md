# Pertemuan 06 — Quality Gate

Branch `feat/pertemuan-06-qr-session`, 2026-08-17. DB MariaDB `kantin_test`. (Pedoman Laravel Boost diadopsi.)

| Command | Exit | Ringkasan |
|---|---:|---|
| `php artisan test` | 0 | **85 passed** (308 assertions) — 8 baru (3 unit token + 5 feature QR/sesi); rantai vertical slice |
| `composer test` (pint+phpstan+test) | 0 | pint · phpstan 0 · 85 passed |
| `npm run build` | 0 | OK |
| `git diff --check` | 0 | bersih |

Demo live (server lokal):
```
GET /q/<token-valid>  -> 302 Location: /kantin/kantin-pusat + Set-Cookie: customer_session=…; httponly; samesite=lax
GET /q/tidak-ada      -> 404 (generik)
```

Vertical slice (rantai v2): `admin.tables.*` → `App\Modules\Admin\Http\Controllers\AdminDiningTableController` (view `admin::tables.*`); `customer.scan` (`/q/{token}`) → `App\Modules\Ordering\Http\Controllers\ResolveTableQrController` via `app/Modules/Ordering/routes/web.php` (`PortalRoutes::web()`); limiter `qr-scan` didaftarkan `OrderingServiceProvider`. Bukti: `screenshots/m6-module-routes.png`.
