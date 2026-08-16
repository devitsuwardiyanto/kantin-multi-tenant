# Pertemuan 03 — Demo Script

## Data demo (`php artisan migrate:fresh --seed`)
- Kantin: **Kantin Pusat** (KTN-PUSAT), 3 meja (M01–M03).
- Tenant **AYAM** (Ayam Geprek Mantul): kategori "Paket Ayam", menu Geprek Original (15.000), Geprek Keju (20.000), modifier "Level Pedas".
- Tenant **KOPI** (Kopi Kita): kategori "Kopi Susu", menu Kopi Susu Gula Aren (18.000), Americano (16.000), modifier "Ukuran".
- Komisi 15% per tenant (effective-dated). Operator `tenant@kantin.test` → role operator tenant AYAM.

## Langkah
1. `php artisan migrate:fresh --seed` (database dev aman).
2. `php artisan db:show` → 39 tabel. `php artisan db:table menus` → struktur.
3. Query bukti: `SELECT display_name FROM tenants;` → 2 tenant.
4. Uji negatif (opsional): INSERT modifier_option tenant lain → gagal FK 1452.
