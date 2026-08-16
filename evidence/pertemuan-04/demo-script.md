# Pertemuan 04 — Demo Script

## Data (`php artisan migrate:fresh --seed`)
- Kantin Pusat; tenant AYAM (operator `tenant@kantin.test`) & KOPI; menu+modifier per tenant.

## Langkah
1. Login `tenant@kantin.test` → buka `/tenant/ayam-pusat/dashboard` → 200 (anggota). Buka `/tenant/kopi-pusat/dashboard` → **403** (bukan anggota).
2. `GET /tenant/ayam-pusat/menus` → hanya menu AYAM (scope). Coba id menu KOPI di URL AYAM → **404** (scoped binding).
3. `PATCH /tenant/ayam-pusat/menus/{id}` {is_available:false} → 200 (policy allow). Menu tenant lain → 404/403.
4. Katalog publik (service `PublicCatalogQuery::forCanteen`) → hanya menu tenant aktif kantin tsb.
5. Job `CountTenantMenus(tenantId)` → context diisi & dibersihkan; dua job berurutan terisolasi.

Semua enforcement server-side; menu tersembunyi bukan authorization.
