# Evidence Pertemuan 13 — Laporan, Rekonsiliasi, Ekspor, Withdrawal
Tanggal: 2026-08-17T11:31:06Z

## Gerbang mutu
```
PHPUnit : 144 passed, 399 assertions (133 -> 144, +11 tes Pertemuan 13)
PHPStan : 0 error (max level)
Pint    : passed
```

Withdrawal: request (available->held via ledger hold) -> approve (withdrawal_debit)
atau reject (release held->available). Satu penarikan aktif per tenant (UNIQUE
active_tenant_lock). Rekonsiliasi: akumulasi ledger == saldo materialisasi.
