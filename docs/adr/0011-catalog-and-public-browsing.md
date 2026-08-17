# ADR-0011 — Katalog tenant, stok, dan public catalog

- Status: Diterima
- Tanggal: 2026-08-17
- Konteks: Katalog dari dua sisi (tenant mengelola; pelanggan menelusuri lintas-tenant). FR-CUS-02/UC-01, FR-CUS-04/UC-04, FR-TEN-02/UC-13, FR-TEN-03/UC-14.

## Keputusan
1. **Sellable predicate** (`Menu::scopeSellable`): `is_available` (keputusan operasional cepat) **dan**
   `stock_qty > 0` (kuantitas transaksional). Keduanya dipisah. Tenant aktif difilter pada query publik.
2. **Manajer menu tenant (Livewire SFC)**: karena update Livewire **tidak** melewati middleware route,
   komponen meng-`booted()` untuk **(a)** memverifikasi ulang membership aktor terhadap `tenantId` (prop
   publik = tidak dipercaya) → abort 403 bila bukan anggota, **(b)** mengisi `TenantContext`. Semua query
   lalu ter-scope otomatis; `tenant_id` menu **auto-fill** dari context saat create.
3. **Stok atomik** (`MenuStockService`): transaksi + `lockForUpdate`; `menu_stock_movements` idempoten
   (unique key) + update `stock_qty` + audit — semua-atau-tidak. Toggle `is_available` cepat + audit.
4. **Public catalog** (`PublicCatalogQuery::browse`, Livewire full-page-embed): bypass `TenantScope`
   **terkontrol** lalu filter canteen (dari sesi QR) + tenant aktif + sellable; **eager load** tenant/category
   (anti N+1, diuji ≤6 query); paginate 16; search/filter **URL-bound** (shareable, tombol back bekerja).
   Harga/stok tampilan = snapshot; checkout membaca ulang (Modul 8–9).
5. **Konvensi Livewire**: SFC (`⚡` prefix) mengikuti starter kit; `wire:key` pada loop; `wire:model.live`;
   validate+authorize di action.

## Ditunda
Upload foto menu + konversi WebP (`intervention/image`) **ditunda** (tanpa menambah dependency tanpa
persetujuan; kolom foto belum ada di skema). DOC-07-001.

## Konsekuensi
- Diuji: katalog hanya canteen sendiri (no cross-canteen leak), unavailable/out-of-stock tersembunyi,
  search, N+1 bounded, create menu tenant_id dari context, toggle/restock atomik+audit, tampering tenantId non-anggota → 403.
