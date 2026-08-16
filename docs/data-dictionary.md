# Data Dictionary — Baseline (Modul 3)

Diturunkan dari `references/ERD_Kantin_Multi_Tenant.svg` + invariant Modul 3.
Catatan: **SQL baseline `00_database` yang disebut modul tidak disertakan** di `references/`
(hanya ERD SVG); skema diturunkan dari ERD (lihat [DOC-03-001](MODULE_REVISION_LOG.md)).

Scope: **P** = platform-scoped · **T** = tenant-owned · **M** = mixed/support.
Uang = `unsignedBigInteger` (Rupiah, integer). Rate = `decimal(6,4)` (exact, bukan float).
Waktu = `timestamp(6)` UTC. Token = `binary(32)` (hash, bukan token mentah).

## Pemetaan ERD → tabel → model

| # | Tabel | Scope | Model | Kunci/Invariant utama |
|--:|---|:--:|---|---|
| 1 | canteens | P | `Canteen` | UQ code, slug |
| 2 | tenants | T | `Tenant` (SoftDeletes) | UQ (canteen_id,code)/(canteen_id,slug); UQ (id,canteen_id) |
| 3 | tenant_balances | T | `TenantBalance` | PK=tenant_id; CHECK saldo ≥ 0 |
| 4 | tenant_bank_accounts | T | — | account_number_cipher (encrypted) + last4; UQ (tenant_id,id) |
| 5 | users | P | `User` | UQ email, phone_e164 |
| 6 | user_canteen_roles | P | — | UQ (user_id,canteen_id,role) |
| 7 | user_tenant_roles | T | `UserTenantRole` | UQ (user_id,tenant_id,role) → sumber TenantContext |
| 8 | dining_tables | P | `DiningTable` | UQ (canteen_id,code); UQ (id,canteen_id) |
| 9 | table_qr_tokens | P | — | UQ token_hash binary(32) |
| 10 | customer_sessions | P | — | PK ULID; UQ session_token_hash |
| 11 | tenant_operating_hours | T | — | UQ (tenant_id,day_of_week) |
| 12 | commission_schemes | T | `CommissionScheme` | effective-dated (valid_from/valid_to); UQ (tenant_id,id) |
| 13 | menu_categories | T | `MenuCategory` | UQ (tenant_id,name); UQ (tenant_id,id) |
| 14 | modifier_groups | T | `ModifierGroup` | min/max_select; UQ (tenant_id,id) |
| 15 | menus | T | `Menu` | **FK komposit (tenant_id,category_id)**; UQ (tenant_id,id) |
| 16 | modifier_options | T | `ModifierOption` | **FK komposit (tenant_id,group_id)** |
| 17 | menu_modifier_groups | T | — | PK (menu_id,modifier_group_id); FK komposit tenant |
| 18 | orders | P | `Order` | **TANPA tenant_id**; UQ public_id/order_number/checkout_key/tracking_token_hash |
| 19 | tenant_orders | T | `TenantOrder` | FK komposit (tenant_id,commission_id); snapshot komisi; UQ (order_id,tenant_id) |
| 20 | order_items | T | `OrderItem` | FK komposit (tenant_id,tenant_order_id)/(tenant_id,menu_id); snapshot harga |
| 21 | order_item_modifiers | T | — | FK komposit (tenant_id,order_item_id); snapshot |
| 22 | menu_stock_movements | T | — | UQ idempotency_key; FK komposit (tenant_id,menu_id) |
| 23 | payments | P | — | UQ order_id/payment_reference/idempotency_key |
| 24 | payment_attempts | P | — | UQ provider_reference; qris_payload |
| 25 | payment_events | P | — | UQ provider_event_id (replay guard); **append-only** |
| 26 | withdrawals | T | `Withdrawal` | UQ idempotency_key; UQ active_tenant_lock (1 aktif/tenant) |
| 27 | ledger_entries | M | — | UQ idempotency_key; **append-only**; delta available/held |
| 28 | notification_deliveries | M | — | UQ (event_key,channel) |
| 29 | outbox_events | M | — | UQ event_key; published_at (after-commit) |
| 30 | audit_logs | M | — | **append-only**; before/after/metadata |

Model dibuat "inti minimal untuk alur pemesanan" (Modul 3). Tabel tanpa model diakses via
query/DB pada modul yang relevan; model menyusul saat fitur dibangun (Modul 5–13).

## Invariant kunci (ditegakkan DB, bukan hanya aplikasi)
- **Anti lintas-tenant**: composite FK `(tenant_id, x_id) → parent(tenant_id, id)` menolak relasi
  child menunjuk resource tenant lain, walau aplikasi salah. Terbukti gagal 1452 (uji negatif).
- **orders tanpa tenant_id**: satu checkout memuat banyak `tenant_orders`; snapshot komisi/harga historis.
- **Idempotency**: UQ pada `checkout_key`, `payments.idempotency_key`, `menu_stock_movements`,
  `ledger_entries`, `withdrawals`, `payment_events.provider_event_id`.
- **Append-only**: payment_events, ledger_entries, audit_logs — koreksi via reversal (Modul 11).
- **Uang integer**: tidak ada FLOAT untuk nominal (menghindari galat pembulatan).
