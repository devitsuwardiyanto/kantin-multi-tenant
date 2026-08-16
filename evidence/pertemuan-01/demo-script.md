# Pertemuan 01 — Demo Script

## Prasyarat
- MariaDB `kantin` + `kantin_test`, Redis 6379 aktif. `.env` terisi (lihat README).

## Langkah demo
1. `composer run dev` (HTTP + queue + Vite). Buka `http://localhost:8000`.
2. Halaman **login** tampil: judul "Log in to your account", opsi *Sign in with a passkey*,
   form Email/Password, *Remember me*, tombol **Log in**, tautan **Sign up** (starter kit Livewire + Fortify, tema Flux gelap).
   - Diverifikasi 2026-08-16 via dev server pada port 8001; `GET /login` → HTTP 200; render benar.
3. **Register**: buka `/register`, buat akun demo (mis. `demo@kantin.test`), submit → redirect ke `/dashboard`.
4. **Dashboard**: halaman terautentikasi tampil. **Logout** dari menu user → kembali ke tampilan tamu.
5. Sehat infra: `php artisan db:show` (MariaDB terhubung) dan `redis-cli -p 6379 ping` → PONG.

## Rute kunci (`php artisan route:list`)
- `GET /login` · `POST /login` · `GET /register` · `POST /register`
- `GET /dashboard` (auth) · passkey & two-factor routes (Fortify/Passkeys)

## Data demo
Belum ada seeder domain pada Pertemuan 1 (hanya skema auth baseline). Akun demo dibuat lewat registrasi.
Tidak ada secret produksi; kredensial demo bersifat lokal.
