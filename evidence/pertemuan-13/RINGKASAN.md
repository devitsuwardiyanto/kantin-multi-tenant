# Evidence Pertemuan 13 — Laporan, Rekonsiliasi, Ekspor, Withdrawal
Tanggal: 2026-08-17T11:31:06Z

## Gerbang mutu
```
PHPUnit : 160 passed, 567 assertions (148 -> 160, +12 tes Pertemuan 13; rantai v2)
PHPStan : 0 error (max level)
Pint    : passed
```

Withdrawal: request (available->held via ledger hold) -> approve (withdrawal_debit)
atau reject (release held->available). Satu penarikan aktif per tenant (UNIQUE
active_tenant_lock). Rekonsiliasi: akumulasi ledger == saldo materialisasi.

## Vertical slice (rantai v2)
- Modul Reporting: `TenantLedgerReport`, `TenantLedgerExportController` (`app/Modules/Reporting/Http/Controllers`),
  komponen `reporting::finance-panel`, halaman `reporting::tenant.finance-panel`, route `tenant.finance` + `tenant.finance.export`
  di `app/Modules/Reporting/routes/tenant.php`.
- Modul Payments: `WithdrawalService`/`WithdrawalException`, komponen `payments::withdrawal-review`, halaman
  `payments::admin.withdrawals`, route `admin.withdrawals.index` di `app/Modules/Payments/routes/admin.php`.
- Diuji: kedua halaman merender komponen modulnya (`assertSeeLivewire`).
- Bukti: `screenshots/m13-reporting-module.png`.
