# Pertemuan 03 — Database Verification

## Skema
39 tabel total di `kantin_test` = 30 baseline domain (ERD) + tabel bawaan (users/cache/jobs/passkeys/migrations/…).
Schema dump struktur: `evidence/pertemuan-03/schema-dump.sql`.

## Invariant terbukti
| Invariant | Bukti |
|---|---|
| Composite FK anti lintas-tenant (menus, modifier_options, order_items, menu_modifier_groups, tenant_orders, menu_stock_movements) | 9 multi-column FK di information_schema; INSERT lintas-tenant → **1452** |
| CHECK saldo ≥ 0 | `chk_tenant_balance_nonneg (available_amount>=0 AND held_amount>=0)` |
| orders tanpa tenant_id | `Schema::hasColumn('orders','tenant_id') === false` (test) |
| 1 order → banyak tenant_orders | test hijau (2 tenant_orders / order) |
| Token hash | `token_hash` = varbinary(32), UNIQUE |
| Uang integer | cast integer (test money) |
| Idempotency | UNIQUE pada checkout_key, payments, stock_movements, ledger, withdrawals, payment_events |

## Migrate/rollback
`migrate:fresh` (up) dan `migrate:rollback` (down) keduanya exit 0 — schema dapat dibangun dari nol dari repo.
