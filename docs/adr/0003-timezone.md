# ADR-0003 — Zona waktu

- Status: Diterima
- Tanggal: 2026-08-16
- Konteks: Modul v7 tidak konsisten — intro menyebut `Asia/Jakarta`, Tahap 4 menyebut
  `Asia/Makassar` (DOC-01-005). Prinsip modul: simpan transaksi dalam UTC, konversi pada presentasi.

## Keputusan
- Penyimpanan waktu transaksi: **UTC** (`APP_TIMEZONE=UTC`, kolom timestamp UTC).
- Presentasi ke pengguna: **`Asia/Jakarta`** (WIB) sebagai default locale `id`.
- `APP_DEBUG=false` di produksi.

## Konsekuensi
- Seragamkan teks modul agar konsisten `Asia/Jakarta` pada lapisan presentasi (DOC-01-005).
- Bila kebutuhan zona waktu tenant per-wilayah muncul, dibuat ADR lanjutan.
