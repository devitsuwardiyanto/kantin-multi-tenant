# Pertemuan 03 — Security/Data Verification

Fokus Modul 3 = integritas data di lapisan DB (isolasi tenant penuh menyusul Modul 4).

| Kontrol | Status |
|---|---|
| Cross-tenant relation ditolak DB (composite FK) | ✅ 1452 (menu↔category, option↔group, item↔menu) |
| tenant_id tidak mass-assignable (model tenant-owned) | ✅ dikeluarkan dari `$fillable`; di-set eksplisit |
| Nomor rekening tidak plaintext | ✅ kolom `account_number_cipher` (encrypted) + `account_last4` |
| Token disimpan sebagai hash | ✅ varbinary(32), bukan token mentah |
| Append-only untuk keuangan/audit | ✅ payment_events, ledger_entries, audit_logs (koreksi via reversal — Modul 11) |

Global scope, policy, dan scoped binding lintas-tenant diuji penuh pada Modul 4.
