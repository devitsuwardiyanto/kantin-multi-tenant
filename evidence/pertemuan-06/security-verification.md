# Pertemuan 06 — Security Verification

| Kontrol | Hasil |
|---|---|
| Token opaque CSPRNG ≥128-bit, base64url | ✅ random_bytes(32); tolak <16 byte |
| Simpan hash SHA-256 raw (bukan token mentah) | ✅ BINARY(32); token mentah sekali-tampil |
| URL publik tak menerima ID numerik | ✅ `/q/1` → 404 (anti-enumeration) |
| Rotasi mencabut token lama segera (transaksi+lock) | ✅ QR lama → 404 setelah rotate; satu aktif/meja |
| Resolve validasi token/meja/canteen status+expiry | ✅ |
| Kegagalan generik (tak bocor keberadaan resource) | ✅ semua invalid → 404 |
| Sesi anonim terpisah + cookie aman | ✅ ULID; HttpOnly, SameSite=Lax, Secure di produksi, TTL 4 jam |
| Rate limiter resolve | ✅ `throttle:qr-scan` (30/menit/IP) |
| Token mentah tak di-log/audit | ✅ audit via id; sanitasi |
