# Pertemuan 02 — Demo Script

## Data demo (deterministik, `php artisan migrate:fresh --seed`)
- Admin: `admin@kantin.test` / `password` (role admin)
- Tenant: `tenant@kantin.test` / `password` (role tenant)
- Password demo bersifat lokal (bukan secret produksi).

## Langkah
1. `composer run dev` → `http://localhost:8000`.
2. **Publik**: buka `/kantin/demo` → layout pelanggan mobile-first (header "Kantin: demo", empty-state katalog). Tanpa login.
3. **Admin**: login `admin@kantin.test` → buka `/admin/dashboard` → layout admin (tabel responsif). Login sebagai tenant lalu buka `/admin/dashboard` → **403**.
4. **Tenant**: login `tenant@kantin.test` → buka `/tenant/demo/dashboard` → layout tenant (sidebar dapat diciutkan pada layar sempit). role admin/null → **403**.
5. Guest membuka `/admin/dashboard` atau `/tenant/demo/dashboard` → diarahkan ke login.

Layout tenant & admin terverifikasi merender (HTTP 200) via feature test; layout customer discreenshot.
