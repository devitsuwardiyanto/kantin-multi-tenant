# Evidence Pertemuan 12 — Kitchen Display System Realtime, Status, Notifikasi
Tanggal: 2026-08-17T10:48:50Z

## Gerbang mutu
```
PHPUnit : 133 passed, 363 assertions (126 -> 133, +7 tes Pertemuan 12)
PHPStan : 0 error (max level)
Pint    : passed
```

State machine tenant_order: pending -> accepted -> preparing -> ready -> completed
(pending/accepted juga -> cancelled). Broadcast: NewTenantOrderReceived (lunas),
TenantOrderStatusChanged (transisi) ke private-channel tenant.{id}.orders (Reverb).
