# Pertemuan 05 — Quality Gate

Branch `feat/pertemuan-05-admin`, 2026-08-17. DB MariaDB `kantin_test`.

| Command | Exit | Ringkasan |
|---|---:|---|
| `php artisan migrate:fresh --seed --env=testing` | 0 | + guard admin (active_lock, primary_lock) |
| `php artisan test` | 0 | **69 passed** (152→ assertions) |
| `composer test` (pint+phpstan+test) | 0 | pint · phpstan 0 · 69 passed |
| `npm run build` | 0 | OK |
| `git diff --check` | 0 | bersih |

Guard DB terbukti: skema komisi aktif ganda → **1062** (uq_commission_active_per_tenant); rekening primary ganda → **1062** (uq_bank_primary_per_tenant).
