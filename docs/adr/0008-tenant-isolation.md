# ADR-0008 — Isolasi multi-tenant berlapis

- Status: Diterima
- Tanggal: 2026-08-17
- Konteks: Shared database multi-tenant aman hanya bila semua jalur akses mematuhi tenant aktif.
  FR-TEN-01; NFR keamanan & privasi. Melengkapi composite FK DB (Modul 3).

## Keputusan — empat lapis pertahanan
1. **TenantContext** (`app/Support/Tenancy/TenantContext.php`) — `scoped()` binding (bukan singleton):
   satu instance per request/job, direset lifecycle berikutnya, dibersihkan di `finally`. `tenant()`/`id()`
   gagal cepat bila kosong.
2. **Global scope** via trait `BelongsToTenant` pada model tenant-owned (Menu, MenuCategory, ModifierGroup,
   ModifierOption, CommissionScheme, TenantOrder, OrderItem, Withdrawal): `where tenant_id = context->id()`
   saat context terisi + auto-fill `tenant_id` saat create. `tenant_id` **tidak** mass-assignable.
3. **Resolver middleware** `SetTenantContext` (alias `tenant`): mengikat `{tenant:slug}`, memverifikasi
   status aktif + membership (`user_tenant_roles`), mengisi context. Tenant nonaktif/non-anggota → 403, tak ada → 404.
4. **Policy + scoped route binding**: `scopeBindings()` mengunci `{menu}` di bawah `{tenant}`; `MenuPolicy`/
   `TenantOrderPolicy`/`WithdrawalPolicy` memeriksa kepemilikan tenant aktif. Cross-tenant → 404/403.

Ditambah **composite FK/unique** (Modul 3) sebagai benteng penyimpanan.

## Bypass terkontrol
Katalog publik lintas-tenant memakai `PublicCatalogQuery` (class khusus, mudah diaudit):
`withoutGlobalScope('tenant')` **eksplisit** lalu filter pengganti canteen + status aktif + available.

## Sifat scope & mitigasi
Scope memfilter hanya saat context terisi (mengikuti cuplikan modul, agar `find`/`refresh`/factory/seeder
tanpa context tetap berfungsi). Karena itu **route internal SELALU mengisi context** (resolver), alur lintas-
tenant memakai bypass eksplisit, dan **write tanpa context gagal-tertutup** via `NOT NULL tenant_id` di DB.

## Konsekuensi
- Middleware tenant coarse Modul 2 (`role:tenant`) digantikan resolver berbasis membership (DOC-04-001).
- Job membawa `tenant_id` (bukan model), membentuk & membersihkan context di `handle()`.
- Matriks allow/deny (scope, binding, policy, bypass, job) diuji di MariaDB nyata — 10 test hijau.
