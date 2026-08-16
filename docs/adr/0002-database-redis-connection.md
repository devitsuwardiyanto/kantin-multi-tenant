# ADR-0002 — Koneksi MariaDB & Redis

- Status: Diterima
- Tanggal: 2026-08-16
- Konteks: Modul v7 memakai `DB_PORT=3307`, `DB_DATABASE=kantin_multi_tenant`, Redis `6380`.
  Environment nyata pengguna (kredensial pada prompt, terverifikasi live) berbeda (DOC-01-004).

## Keputusan
- MariaDB: `DB_CONNECTION=mysql`, host `127.0.0.1`, port **3306**, database **`kantin`**,
  user **`kantin`**, password disimpan hanya di `.env` (tidak di-commit). Terverifikasi:
  `mysql -h127.0.0.1 -P3306 -ukantin` → MariaDB **11.4.5**, db `kantin` ada.
- Redis: `127.0.0.1:**6379**` (terverifikasi `PING → PONG`). Prefix key memuat namespace
  aplikasi + environment agar dev/test tidak bertabrakan; **tidak** memakai `FLUSHDB` global.
- Database pengujian **terpisah** dari development (mis. `kantin_test`) agar `migrate:fresh`/seed
  tidak menyentuh data kerja. Hak `CREATE`/akses DB test diverifikasi saat scaffold.
- `env()` hanya dipanggil dari `config/*`; kode aplikasi membaca via `config()`.

## Konsekuensi
- Revisi modul Tahap 4 & cuplikan `.env.example` (DOC-01-004).
- `.env` nyata tidak masuk Git; `.env.example` hanya placeholder aman.
