# Evidence Pertemuan 12 — Kitchen Display System Realtime, Status, Notifikasi
Tanggal: 2026-08-17T10:48:50Z

## Gerbang mutu
```
PHPUnit : 148 passed, 515 assertions (139 -> 148, +9 tes Pertemuan 12; rantai v2)
PHPStan : 0 error (max level)
Pint    : passed
```

State machine tenant_order: pending -> accepted -> preparing -> ready -> completed
(pending/accepted juga -> cancelled). Broadcast: NewTenantOrderReceived (lunas),
TenantOrderStatusChanged (transisi) ke private-channel tenant.{id}.orders (Reverb).

## Vertical slice (rantai v2)
- Event `NewTenantOrderReceived`/`TenantOrderStatusChanged` di `app/Modules/Kitchen/Events` (Payments memicu
  `NewTenantOrderReceived` saat settlement — komunikasi lintas modul via event); `TenantChannels` di
  `app/Modules/Kitchen/Realtime`.
- Channel privat `tenant.{tenantId}.orders` didaftarkan `KitchenServiceProvider::boot()` (bukan `routes/channels.php`,
  dan bukan file route modul agar tetap aktif saat `route:cache`).
- Komponen `kitchen::kitchen-board`, halaman `kitchen::tenant.kitchen-board`, route `tenant.kitchen` di
  `app/Modules/Kitchen/routes/tenant.php`; diuji `assertSeeLivewire` + channel terdaftar.
- Bukti: `screenshots/m12-kitchen-module.png`.
