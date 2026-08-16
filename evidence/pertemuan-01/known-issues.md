# Pertemuan 01 — Known Issues

| # | Severity | Isu | Dampak | Workaround / Status | Owner |
|---|---|---|---|---|---|
| 1 | Low | `install:broadcasting` gagal pada langkah npm (TTY tidak tersedia di lingkungan non-interaktif) | Reverb terpasang, tapi `laravel-echo`/`pusher-js` & `REVERB_*` env harus ditambah manual | **Resolved**: `npm install laravel-echo pusher-js` + isi `REVERB_*` di `.env`; didokumentasikan di README | dev |
| 2 | Low | Branch protection tidak tersedia pada repo **private** plan Free | — | **Resolved**: repo dijadikan publik (atas persetujuan pemilik) sehingga protection aktif | dev |
| 3 | Info | Port 8000 dipakai proses lain (ServBay) saat demo | `php artisan serve` default bentrok | Gunakan port alternatif (mis. 8001) atau hentikan listener 8000 | dev |
| 4 | Info | DB `kantin_test` perlu dibuat admin (user `kantin` tanpa privilege CREATE) | Test gagal bila DB test belum ada | **Resolved**: DB dibuat + grant; perintah ada di README | dev |
