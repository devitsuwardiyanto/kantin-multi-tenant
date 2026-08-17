# Skrip Demo — Kantin Multi-Tenant

Alur demo end-to-end memetakan Modul 1–14. Jalankan lokal:

```bash
php artisan migrate:fresh --seed   # data demo: kantin "Kantin Pusat", tenant AYAM & KOPI
php artisan serve                  # http://127.0.0.1:8000
php artisan reverb:start           # (opsional) realtime KDS
php artisan queue:work             # (opsional) job async
```

Akun demo (password: `password`): `admin@kantin.test` (pengelola), `operator@kantin.test` (operator tenant AYAM).

## Alur

1. **Scan meja (pelanggan).** Terbitkan token QR meja (Admin → Meja → QR, atau tinker `QrTokenService::issue`), buka `/{q}/<token>` → sesi anonim + cookie, redirect ke katalog `kantin/kantin-pusat`. *(Modul 6)*
2. **Katalog & keranjang.** Cari/ filter menu lintas tenant; "+ Tambah" → keranjang (Redis) dengan revalidasi harga/stok. *(Modul 7–8)*
3. **Checkout.** "Lanjut ke Pembayaran" → order atomik, split per tenant + snapshot komisi; halaman status pesanan. *(Modul 9)*
4. **Pembayaran QRIS (sandbox).** "Bayar dengan QRIS" → payload EMVCo dinamis; "Simulasi Bayar" **atau** kirim webhook ber-signature:
   ```bash
   BODY='{"event_id":"demo-1","payment_reference":"<REF>","status":"success"}'
   SIG=$(printf '%s' "$BODY" | openssl dgst -sha256 -hmac "$QRIS_WEBHOOK_SECRET" | sed 's/^.*= //')
   curl -X POST http://127.0.0.1:8000/webhooks/qris -H "X-Qris-Signature: $SIG" -H 'Content-Type: application/json' -d "$BODY"
   ```
   → status `paid` + settlement (ledger split → saldo tenant). *(Modul 10–11)*
5. **Dapur (operator tenant).** Login `operator@kantin.test` → `tenant/ayam-pusat/kitchen` → pesanan lunas muncul; transisikan `Baru → Diterima → Disiapkan → Siap → Selesai` (realtime bila Reverb aktif). *(Modul 12)*
6. **Keuangan & penarikan (operator tenant).** `tenant/ayam-pusat/finance` → saldo, ringkasan, **rekonsiliasi cocok**, ekspor CSV; ajukan penarikan (butuh rekening verified dari Admin). *(Modul 13)*
7. **Peninjauan penarikan (pengelola).** Login `admin@kantin.test` → `admin/withdrawals` → Setujui/Tolak. *(Modul 13)*

## Poin yang ditonjolkan
- Isolasi multi-tenant berlapis; uang integer Rupiah; event/ledger append-only + reversal.
- Idempotensi (checkout_key, provider_event_id, ledger idempotency_key, satu withdrawal aktif/tenant).
- Webhook ber-signature HMAC (raw body) + rate limit + header keamanan.
- Uji: **146 test** (MariaDB + Redis nyata), termasuk **E2E capstone** (scan→tarik) — `php artisan test`.
