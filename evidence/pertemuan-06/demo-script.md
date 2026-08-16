# Pertemuan 06 — Demo Script

## Prasyarat
`php artisan migrate:fresh --seed` (Kantin Pusat, meja M01–M03). Login `admin@kantin.test`.

## Langkah
1. Admin → `/admin/tables` → daftar meja. Tambah meja (kode unik/kantin).
2. Klik **Terbitkan/Rotate QR** → halaman QR menampilkan **URL sekali-tampil** (`/q/{token}`).
   (Raster QR gambar ditunda — arahkan generator QR ke URL ini; lihat DOC-06-001.)
3. Buka URL `/q/{token}` (simulasi scan) → redirect ke katalog `/kantin/{slug}` + cookie `customer_session` (HttpOnly, SameSite=Lax).
4. Rotate lagi → URL lama → **404**. Token acak/ID numerik → **404**.

Catatan: jangan menyalin token asli ke screenshot/log; gunakan token dummy pada dokumentasi.
