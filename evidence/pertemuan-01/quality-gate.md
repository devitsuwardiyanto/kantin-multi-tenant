# Pertemuan 01 — Quality Gate

Dijalankan pada branch `feat/pertemuan-01-setup`, 2026-08-16, root repository. DB test: MariaDB `kantin_test`.

| Command | Exit | Ringkasan |
|---|---:|---|
| `php artisan --version` | 0 | Laravel Framework 13.25.0 |
| `php artisan db:show` | 0 | MariaDB 11.4.5, connection mysql, db `kantin`, host 127.0.0.1:3306 |
| `redis-cli -p 6379 ping` | 0 | PONG |
| `php artisan package:discover` | 0 | 20+ package discovered, tanpa error (reverb env terisi) |
| `php artisan migrate:fresh --seed --env=testing` | 0 | 5 migrasi + seed pada `kantin_test` |
| `php artisan test` | 0 | **33 passed** (81 assertions), ~3.8s |
| `composer test` (config:clear+pint+phpstan+test) | 0 | pint passed · phpstan 0 errors · 33 passed |
| `./vendor/bin/pint --test` | 0 | passed |
| `npm run build` | 0 | Vite/Rolldown build OK (app-*.css/js, fonts) |
| `composer validate --strict` | 0 | `./composer.json is valid` |
| `composer audit` | 0 | No security vulnerability advisories found |
| `npm audit` | 0 | found 0 vulnerabilities |
| `git diff --check` | 0 | (dijalankan sebelum commit; tidak ada whitespace error) |

## Toolchain terverifikasi
```
PHP 8.3.20 · Composer 2.7.1 · Node v22.14.0 · npm 10.9.2 · Git 2.50.1
MariaDB client 11.4.5 · redis-cli 7.2.6
Laravel installer 5.25 (global) · laravel/framework 13.25.0 · livewire/livewire 4.1 · laravel/reverb ^1.0
Ekstensi PHP: pdo_mysql, redis, mbstring, openssl, ctype, curl, fileinfo, xml, tokenizer, bcmath
```
