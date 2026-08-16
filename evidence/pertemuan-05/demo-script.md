# Pertemuan 05 — Demo Script

## Akun (`php artisan migrate:fresh --seed`)
- Admin/pengelola: `admin@kantin.test` / `password` (manager Kantin Pusat).

## Langkah
1. Login admin → `/admin/tenants` → daftar tenant (AYAM, KOPI).
2. **Buat tenant**: `/admin/tenants/create` → isi nama/kode/slug/komisi% → tersimpan dengan komisi aktif + balance 0 (atomik). Kode duplikat per kantin → ditolak.
3. **Kelola tenant** (`/admin/tenants/{id}`): jadwalkan komisi baru (effective-dated) → timeline tarif; tambah rekening (nomor tersimpan terenkripsi, hanya last4 tampil), verifikasi, set primary; tugaskan/cabut role (owner terakhir dilindungi).
4. Bukti guard DB: dua skema aktif / dua primary → gagal 1062.

Semua aksi diaudit (actor/entity/action/before/after tersanitasi).
