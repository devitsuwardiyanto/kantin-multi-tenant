# ADR-0005 — Framework testing & database uji

- Status: Diterima
- Tanggal: 2026-08-16
- Konteks: Modul memperbolehkan "PHPUnit atau Pest secara konsisten". Prompt mewajibkan MariaDB nyata
  untuk integration/locking/concurrency/constraint test (SQLite memory tidak membuktikan perilaku itu),
  dan database test terpisah dari development.

## Keputusan
- Framework: **PHPUnit** (`phpunit/phpunit ^12`), konsisten sepanjang 14 pertemuan. `php artisan test`
  memakai testsuite `Unit` dan `Feature` (lihat `phpunit.xml`).
- Database uji: **MariaDB** pada database **`kantin_test`** (bukan sqlite `:memory:`). `phpunit.xml`
  meng-override `DB_CONNECTION=mysql`, `DB_DATABASE=kantin_test`; kredensial user dibaca dari `.env`.
- `.env.testing` (tidak di-commit) menunjuk `kantin_test` agar `php artisan ...--env=testing` aman
  terhadap database dev `kantin`.
- Gate `composer test` = `config:clear` → `pint --test` → `phpstan analyse` → `php artisan test`.

## Konsekuensi
- Test locking/concurrency/constraint (Modul 4, 9, 11) berjalan pada MariaDB nyata sejak awal.
- CI menyediakan service MariaDB 11.4 + Redis 7 terisolasi dengan `kantin_test` ephemeral.
- Baseline Pertemuan 1: 33 test starter kit hijau pada `kantin_test`.
