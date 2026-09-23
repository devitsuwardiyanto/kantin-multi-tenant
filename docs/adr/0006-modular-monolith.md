# ADR-0006 — Modular monolith & registrasi modul

- Status: Diterima
- Tanggal: 2026-08-16
- Konteks: Proyek 14 pertemuan butuh pemisahan domain tanpa kompleksitas microservice
  (satu deployment, satu database, transaksi lokal). Modul 2 (FR-TEN-01 / UC-19).

## Keputusan
1. Enam bounded context sebagai folder `app/Modules/{Admin,Catalog,Ordering,Payments,Kitchen,Reporting}`,
   masing-masing **vertical slice**: `{Actions,Data,Services}`, `Http/Controllers`, `routes/{portal}.php`,
   `resources/views` (+ `livewire/`) dan `{Modul}ServiceProvider` turunan `App\Modules\ModuleServiceProvider`.
   Model Eloquent, middleware, layout/komponen UI tetap shared kernel di lokasi standar Laravel.
2. Satu ServiceProvider per modul, terdaftar di `bootstrap/providers.php`. `register()` untuk binding
   container, `boot()` untuk route/event/policy.
3. Route dipisah per **konteks pengguna** (customer/tenant/admin). Grup portal (prefix/name/middleware)
   didefinisikan tunggal di `App\Support\Routing\PortalRoutes`, dipakai `bootstrap/app.php` (route inti)
   dan file route modul (route fitur) — modul menyumbang route, portal menentukan keamanannya.
4. Authorization server-side via middleware `role` (`EnsureUserHasRole`) → 403 untuk role/status
   yang tidak cocok. Menu tersembunyi bukan authorization.
5. Kontrak role interim: `users.role` + `users.status` + `User::hasRole()` (diuji). Policy & relasi
   role penuh diformalkan Modul 4–5. `hasRole()` adalah kontrak milik aplikasi (bukan package eksternal).
6. Komunikasi lintas modul memakai event/interface, bukan akses langsung tabel/controller modul lain.

## Konsekuensi
- `bootstrap/providers.php` & `bootstrap/app.php` menjadi titik perakitan modul; file fitur dicari di satu
  folder modul (mengurangi lompatan antara `app/Http`, `resources/views`, `routes`).
- Alternatif ditolak: memindahkan model ke modul — `Order`/`Tenant` dipakai lintas modul, sehingga
  kepemilikan tunggal hanya memindahkan coupling; factory/policy discovery juga mengandalkan `App\Models`.
- Model binding tenant/canteen (`scopeBindings`) ditunda ke Modul 4 (DOC-02-001); parameter route
  sementara berupa string.
- Autoload `App\Modules\*` mengikuti PSR-4 `App\` → `app/` (tanpa perubahan composer.json).
