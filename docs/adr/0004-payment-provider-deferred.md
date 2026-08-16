# ADR-0004 — Pemilihan payment provider (keputusan tertunda)

- Status: Tertunda (Proposed / pending decision)
- Tanggal: 2026-08-16
- Konteks: Aplikasi memerlukan pembayaran QRIS dinamis (Modul 10–11). Prompt melarang memasang
  beberapa provider sekaligus dan mensyaratkan: bila provider belum dipilih, gunakan
  `FakeQrisGateway` untuk alur lokal dan buat ADR berstatus tertunda.

## Keputusan (sementara)
- Definisikan kontrak `PaymentGateway` (interface) — Modul 10.
- Implementasi default lokal: **`FakeQrisGateway`** (deterministik, untuk dev/test/demo).
- **Tidak** memasang SDK/kredensial provider nyata sampai provider dipilih pengguna.
- Tidak mengklaim telah menguji sandbox nyata sebelum benar-benar dijalankan.

## Opsi yang menunggu keputusan
- (mis.) Midtrans / Xendit / DOKU / provider QRIS lain — pilih satu, lalu buat ADR keputusan final
  yang menggantikan status ini menjadi "Diterima".

## Konsekuensi
- Webhook, signature verification, dan settlement (Modul 11) diuji terhadap event fake yang
  meniru bentuk provider, dengan verifikasi signature dari raw body tetap wajib.
