# Evidence Pertemuan 14 — Hardening, E2E, Deployment, Demo (PENUTUP)
Tanggal: 2026-08-17T13:08:56Z

## Gerbang mutu
```
PHPUnit : 163 passed, 593 assertions (160 -> 163, +3 tes Pertemuan 14; rantai v2)
PHPStan : 0 error (max level)
Pint    : passed
```

Deliverable: SecurityHeaders middleware (global) + rate limit webhook;
E2E capstone (scan->pesan->bayar->dapur->settle->tarik) satu skenario;
docs/DEPLOYMENT.md + docs/DEMO.md.

## Vertical slice & hardening (rantai v2)
- `SecurityHeaders` tetap global di `bootstrap/app.php` (shared kernel, berlaku untuk seluruh respons).
- Limiter `qris-webhook` didaftarkan `PaymentsServiceProvider::boot()` dan dipasang pada route webhook
  modul (`app/Modules/Payments/routes/web.php`); diuji langsung (limiter terdaftar + middleware route).
- `php artisan route:cache` (langkah `docs/DEPLOYMENT.md`) diverifikasi: route semua modul — termasuk route
  closure — ada di cache, dan channel `tenant.{tenantId}.orders` tetap terdaftar (didaftarkan provider,
  bukan file route). Cache dibersihkan kembali setelah verifikasi.
- Bukti: `screenshots/m14-hardening-module.png`.
