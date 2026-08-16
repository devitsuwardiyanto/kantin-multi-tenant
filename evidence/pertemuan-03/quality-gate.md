# Pertemuan 03 — Quality Gate

Branch `feat/pertemuan-03-database`, 2026-08-16. DB MariaDB `kantin_test`.

| Command | Exit | Ringkasan |
|---|---:|---|
| `php artisan migrate:fresh --env=testing` | 0 | 6 migrasi domain (30 tabel baseline) DONE |
| `php artisan migrate:rollback --env=testing` | 0 | down() simetris; seluruh tabel turun tanpa error |
| `php artisan migrate:fresh --seed --env=testing` | 0 | seeder 1 canteen/2 tenant/4 menu/4 opsi/3 meja |
| db:seed (run ke-2) | 0 | idempoten — jumlah baris tetap |
| `php artisan test` | 0 | **50 passed** (115 assertions) |
| `composer test` (pint+phpstan+test) | 0 | pint passed · phpstan 0 · 50 passed |
| `npm run build` | 0 | OK |
| `git diff --check` | 0 | bersih |

Negatif (DB, MariaDB nyata):
- modifier_option → group tenant lain: **ERROR 1452** (composite FK)
- menu → category tenant lain: **ERROR 1452**
- duplikat (canteen_id, code) tenant: **1062**
- duplikat idempotency_key stock movement: **1062**
