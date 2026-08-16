# ADR-0006 — Modular monolith & registrasi modul

- Status: Diterima
- Tanggal: 2026-08-16
- Konteks: Proyek 14 pertemuan butuh pemisahan domain tanpa kompleksitas microservice
  (satu deployment, satu database, transaksi lokal). Modul 2 (FR-TEN-01 / UC-19).

## Keputusan
1. Enam bounded context sebagai folder `app/Modules/{Admin,Catalog,Ordering,Payments,Kitchen,Reporting}`,
   masing-masing `{Actions,Data,Services}` + `{Modul}ServiceProvider`.
2. Satu ServiceProvider per modul, terdaftar di `bootstrap/providers.php`. `register()` untuk binding
   container, `boot()` untuk route/event/policy.
3. Route dipisah per **konteks pengguna** (customer/tenant/admin), diregistrasi terpusat di
   `bootstrap/app.php` (`withRouting(then:)`) dengan prefix/name/middleware konsisten.
4. Authorization server-side via middleware `role` (`EnsureUserHasRole`) → 403 untuk role/status
   yang tidak cocok. Menu tersembunyi bukan authorization.
5. Kontrak role interim: `users.role` + `users.status` + `User::hasRole()` (diuji). Policy & relasi
   role penuh diformalkan Modul 4–5. `hasRole()` adalah kontrak milik aplikasi (bukan package eksternal).
6. Komunikasi lintas modul memakai event/interface, bukan akses langsung tabel/controller modul lain.

## Konsekuensi
- `bootstrap/providers.php` & `bootstrap/app.php` menjadi titik perakitan modul.
- Model binding tenant/canteen (`scopeBindings`) ditunda ke Modul 4 (DOC-02-001); parameter route
  sementara berupa string.
- Autoload `App\Modules\*` mengikuti PSR-4 `App\` → `app/` (tanpa perubahan composer.json).
