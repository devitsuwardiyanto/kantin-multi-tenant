# Pertemuan 07 — Demo Script

## Data (`php artisan migrate:fresh --seed`)
Kantin Pusat; tenant AYAM (Geprek Original/Keju) & KOPI (Kopi Susu Gula Aren/Americano).

## Publik (tanpa login)
1. Buka `/kantin/kantin-pusat` (atau scan QR meja → redirect ke sini) → **katalog** menampilkan menu kedua tenant dengan nama tenant, kategori, harga Rupiah.
2. Ketik di **Cari menu…** → daftar tersaring (URL menyimpan query). Pilih **tenant** pada dropdown → hanya menu tenant itu.

## Tenant (login `tenant@kantin.test`)
3. Buka `/tenant/ayam-pusat/menu-manager` → tambah kategori, buat menu (tenant_id otomatis), **Tandai habis/Aktifkan**, **+10 stok** (movement tercatat).
4. Menu yang ditandai habis / stok 0 hilang dari katalog publik.

Catatan: foto menu (WebP) ditunda — lihat DOC-07-001.
