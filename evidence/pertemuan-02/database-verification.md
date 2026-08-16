# Pertemuan 02 — Database Verification

## Perubahan schema
Migrasi `2026_08_16_000001_add_role_and_status_to_users_table`:
- `users.role` VARCHAR NULL (admin|tenant|null)
- `users.status` VARCHAR NOT NULL DEFAULT 'active' (active|suspended)

`tenant_id` **belum** ditambahkan (isolasi tenant = Modul 4). Tidak ada tabel domain lain (Modul 3).

## Verifikasi seed demo (dev `kantin`)
```
email                 role    status
admin@kantin.test     admin   active
tenant@kantin.test    tenant  active
```
role/status di-set via penetapan atribut langsung (bukan mass assignment — kolom tidak fillable).
