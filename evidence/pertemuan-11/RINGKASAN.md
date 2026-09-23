# Evidence Pertemuan 11 — Webhook Idempoten, Settlement, Split, Ledger, Reversal
Tanggal: 2026-08-17T10:30:08Z

## Gerbang mutu
```
PHPUnit : 139 passed, 489 assertions (127 -> 139, +12 tes Pertemuan 11; rantai v2)
PHPStan : 0 error (max level)
Pint    : passed
```

## Vertical slice (rantai v2)
- `QrisWebhookController` di `app/Modules/Payments/Http/Controllers`; route publik `POST /webhooks/qris`
  (`webhooks.qris`) di `app/Modules/Payments/routes/web.php` via `PortalRoutes::web()`.
- Pengecualian CSRF `webhooks/*` didaftarkan `PaymentsServiceProvider::boot()` (`PreventRequestForgery::except`),
  bukan `bootstrap/app.php`; diuji langsung (test HTTP melewati CSRF) — gagal bila baris provider dinonaktifkan.
- Pengecualian enkripsi cookie `order_tracking` (Ordering, P9) kini juga diuji eksplisit.
- Bukti: `screenshots/m11-payments-webhook-module.png`.
