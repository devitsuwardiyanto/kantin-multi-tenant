# ADR-0010 — QR token opaque & sesi pelanggan anonim

- Status: Diterima
- Tanggal: 2026-08-17
- Konteks: QR meja = credential publik mudah disalin. FR-CUS-01/UC-02; FR-ADM-02/UC-22.

## Keputusan
1. **Token opaque** (`OpaqueToken`): CSPRNG `random_bytes(32)` (256-bit), base64url; disimpan sebagai
   **SHA-256 raw (BINARY 32)**; token mentah ditampilkan **sekali** saat penerbitan. Tolak < 128-bit.
   ID/UUID meja **bukan** secret; URL publik hanya menerima token (bukan ID numerik).
2. **Rotasi/revokasi** (`QrTokenService`): dalam transaksi + `lockForUpdate`, cabut token aktif lama
   lalu terbitkan baru. QR lama gagal **segera** setelah rotasi. Satu token aktif per meja.
3. **Resolve** (`ResolveTableScan`): lookup by hash → validasi status/expiry token + status meja + status
   canteen. Kegagalan **generik 404** (tidak membocorkan apakah resource pernah ada; anti-enumeration).
4. **Sesi anonim** (`CustomerSession`, PK ULID): token sesi kedua (hash disimpan), cookie `customer_session`
   **HttpOnly + SameSite=Lax + Secure di produksi**, TTL 4 jam. Terikat composite canteen+meja hasil scan.
   Order historis tetap di DB dengan tracking token tersendiri (bukan bergantung cookie).
5. **Rate limiter** `qr-scan` (30/menit/IP) pada `GET /q/{token}` mengurangi brute force.
6. Token mentah tidak pernah di-log; audit mencatat rotasi via id/hash, bukan token mentah.

## QR raster (ditunda)
Modul menyarankan `chillerlan/php-qrcode` untuk gambar cetak. Sesuai pedoman (tidak menambah dependency
tanpa persetujuan) dan pernyataan modul bahwa keamanan token **tidak** bergantung library gambar, raster
QR **ditunda** (DOC-06-001). Halaman QR menampilkan URL sekali-tampil; generator QR eksternal/library
dapat diarahkan ke URL tersebut setelah dependency disetujui.

## Konsekuensi
- Verifikasi live: `/q/{token}` valid → 302 + cookie HttpOnly/SameSite=Lax; invalid → 404.
- Diuji: token beda tiap issue, hash 32 byte, unik per canteen, rotate mencabut lama, sesi terikat, invalid/expired/revoked/nonaktif → 404, anti-enumeration.
