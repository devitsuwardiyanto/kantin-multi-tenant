# Pertemuan 05 — Quality Gate

Branch `feat/pertemuan-05-admin`, 2026-08-17; dibangun ulang pada `feat/pertemuan-05-admin-v2` (controller/request/view/route milik modul Admin). DB MariaDB `kantin_test`.

| Command | Exit | Ringkasan |
|---|---:|---|
| `php artisan migrate:fresh --seed --env=testing` | 0 | + guard admin (active_lock, primary_lock) |
| `php artisan test` | 0 | **77 passed** (264 assertions); `AdminManagementTest` 9 passed (23); `CommissionScheduleBoundaryTest` 3 passed (14) |
| `composer test` (pint+phpstan+test) | 0 | pint · phpstan 0 · 77 passed |
| `npm run build` | 0 | OK |
| `git diff --check` | 0 | bersih |

Guard DB terbukti: skema komisi aktif ganda → **1062** (uq_commission_active_per_tenant); rekening primary ganda → **1062** (uq_bank_primary_per_tenant).

Batas pergantian komisi (DOC-05-006): interval setengah-terbuka `[valid_from, valid_to)` — di setiap instan sekitar waktu efektif (−1 hari, −1 detik, −1 µs, tepat, +1 detik) tepat satu skema berlaku. Test yang sama **gagal 2/3** bila versi lama ditutup `subSecond()` (celah ±1 detik tanpa skema).
