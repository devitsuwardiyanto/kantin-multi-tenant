# Skrip Demo — Kantin Multi-Tenant

Alur demo end-to-end memetakan Modul 1–14 dan ke-23 use case SRS v2 (daftar periksa lengkap:
`docs/REQUIREMENTS_TRACEABILITY.md`, bagian “Uji penerimaan browser”). Jalankan lokal di empat jendela terminal:

```bash
php artisan migrate:fresh --seed   # data demo: kantin "Kantin Pusat", tenant AYAM & KOPI, grup modifier, pre-order aktif
php artisan serve                  # http://127.0.0.1:8000
php artisan queue:work             # notifikasi WhatsApp sandbox (UC-12) dan ekspor laporan (UC-17)
php artisan reverb:start           # realtime KDS dan pelacakan pesanan (UC-15, UC-09)
```

Akun demo (password: `password`): `admin@kantin.test` (pengelola kantin), `tenant@kantin.test` (operator tenant AYAM).
Pakai tiga jendela peramban (pengelola, tenant, dan jendela penyamaran untuk pelanggan).

## Alur

1. **Masuk (UC-19).** Sandi salah ditolak dengan pesan generik; `tenant@kantin.test` diarahkan ke dasbor tenant. *(Modul 5)*
2. **Pengelola: tenant & rekening (UC-21).** Admin → Tenant → Ubah komisi (Ayam Geprek Mantul) → tambah rekening → **Verifikasi**. Rekening terverifikasi adalah prasyarat penarikan. *(Modul 5, 14)*
3. **Pengelola: meja & QR (UC-22).** Admin → Meja → Terbitkan & unduh QR → URL `/q/<token>` tampil sekali. *(Modul 6)*
4. **Pelanggan: pindai & telusuri (UC-02, UC-01, UC-11).** Buka URL QR → “Selamat datang, Meja 1” → katalog per tenant dengan estimasi antrean. *(Modul 6–7)*
5. **Kustomisasi & keranjang (UC-04, UC-03).** “+ Tambah” Kopi Susu Gula Aren → pilih Ukuran (wajib) + Topping + catatan; tambah Geprek Keju → keranjang dua tenant dengan pajak dan biaya layanan. *(Modul 8)*
6. **Checkout & QRIS (UC-05, UC-07).** Checkout → nama + WhatsApp → Konfirmasi → halaman pesanan dengan QRIS dan hitung mundur. *(Modul 9–10)*
7. **Webhook pembayaran (UC-08, UC-10, UC-12).** Kirim webhook ber-signature (nominal wajib sama dengan tagihan):
   ```bash
   BODY='{"event_id":"demo-1","payment_reference":"<REF>","status":"settlement","amount":<NOMINAL>}'
   SIG=$(printf '%s' "$BODY" | openssl dgst -sha256 -hmac "$QRIS_WEBHOOK_SECRET" | sed 's/^.*= //')
   curl -X POST http://127.0.0.1:8000/webhooks/qris -H "X-Qris-Signature: $SIG" -H 'Content-Type: application/json' -d "$BODY"
   ```
   → “Pembayaran terverifikasi”, alokasi split (Σ = nominal), notifikasi WhatsApp sandbox di log. *(Modul 11–12)*
8. **Dapur & pelacakan (UC-15, UC-09).** Tenant → Dapur → Terima → Selesai; halaman pesanan pelanggan berubah menjadi SIAP DIAMBIL tanpa muat ulang. *(Modul 12)*
9. **Pre-order (UC-06).** Pesanan kedua dengan mode Pesan dulu → pilih slot → sub-pesanan `scheduled`, dilepas ke dapur otomatis. *(Modul 9)*
10. **Menu (UC-13, UC-14).** Tenant → Kelola Menu → sakelar ketersediaan → katalog pelanggan menampilkan HABIS ≤ 5 detik. *(Modul 7)*
11. **Keuangan tenant (UC-16, UC-17, UC-18).** Laporan (omset, menu terlaris, jam sibuk) → Ekspor .xlsx / PDF → Rekonsiliasi per referensi pembayaran. *(Modul 13)*
12. **Penarikan (UC-20, UC-23).** Tenant → Penarikan → Rp100.000 → pengelola → Pencairan Dana → unggah bukti transfer → Setujui. *(Modul 13)*

## Poin yang ditonjolkan
- Isolasi multi-tenant berlapis; uang integer Rupiah; event/ledger append-only + reversal.
- Idempotensi (checkout_key, provider_event_id, ledger idempotency_key, satu withdrawal aktif/tenant).
- Webhook ber-signature HMAC (raw body) + nominal dicocokkan + rate limit + header keamanan.
- Uji: **292 test** (MariaDB + Redis nyata) termasuk E2E capstone dan `RequirementsTraceabilityTest`;
  uji penerimaan browser **23/23 use case PASS** — `php artisan test`.
