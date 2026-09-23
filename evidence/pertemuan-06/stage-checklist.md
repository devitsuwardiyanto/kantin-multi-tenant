# Pertemuan 06 — Stage Checklist

Output: CRUD meja + generate/rotate QR, resolver token hash, CustomerSession + cookie aman, landing katalog.

| Tahap | Aktivitas | Status | Bukti |
|---|---|---|---|
| 1 | Lifecycle meja & QR | ✅ | ADR-0010; skema M3 (dining_tables/table_qr_tokens/customer_sessions) |
| 2 | CRUD meja & authorization | ✅ | AdminDiningTableController + DiningTablePolicy; kode unik/canteen (test) |
| 3 | Generator token opaque | ✅ | OpaqueToken (CSPRNG base64url, SHA-256 32 byte, tolak <128-bit) |
| 4 | Generate/rotate QR | ✅ | QrTokenService (lock+revoke+issue); QR lama gagal segera |
| 5 | Resolve scan & sesi anonim | ✅ | ResolveTableScan; /q/{token}→302+cookie HttpOnly/SameSite; sesi ULID terikat canteen+meja |
| 6 | Security & UX testing | ✅ | invalid/expired/revoked/nonaktif→404 generik; anti-enumeration; rate limiter |
| 7 | Dokumentasi & commit | ✅ | ADR-0010, DOC-06-001..003, PR + tag pertemuan-06 |
