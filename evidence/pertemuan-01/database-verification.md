# Pertemuan 01 — Database Verification

## Koneksi
- Dev: `php artisan db:show` → MariaDB **11.4.5**, connection `mysql`, database `kantin`, 127.0.0.1:3306, user `kantin`.
- Test: MariaDB `kantin_test` (terpisah), dibuat oleh admin + grant ke user `kantin`.

## Isolasi & keamanan data
- Database `kantin` terbukti **kosong (0 tabel)** sebelum migrasi:
  `SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='kantin'` → 0.
- Schema tak-terkait `test_wbs` (33 tabel milik proyek lain) **tidak** disentuh: migrasi hanya menargetkan
  database `kantin`/`kantin_test`. `migrate:fresh` **tidak** dijalankan pada dev `kantin`.

## Migrasi baseline (starter kit) pada `kantin_test`
```
0001_01_01_000000_create_users_table
0001_01_01_000001_create_cache_table
0001_01_01_000002_create_jobs_table
2024_01_01_000000_create_passkeys_table
2025_08_14_170933_add_two_factor_columns_to_users_table
```
Semua DONE (exit 0) via `php artisan migrate:fresh --seed --env=testing`.

## Catatan
- Skema domain (tenant/order/payment/ledger) belum ada — dijadwalkan Modul 3 (ERD baseline).
