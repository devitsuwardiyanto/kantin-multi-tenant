# Implementation Matrix — Kantin Multi-Tenant

Sumber: `references/Modul_Praktikum_Kantin_Multi_Tenant_Laravel_13_Mentor_Tutorial_v7.docx`
(14 modul × 7 tahap = 98 tahap). Peta ini adalah indeks; kriteria selesai penuh ada di tiap modul.

Konvensi eksekusi (dari prompt tugas, mengganti strategi `develop`/`week-NN` pada modul — lihat [DOC-01-003](MODULE_REVISION_LOG.md)):
branch `feat/pertemuan-NN-slug` dari `main` → PR → CI → merge → annotated tag `pertemuan-NN` → GitHub Release.

| Mg | Branch kerja | Tag | Output utama yang harus terbukti |
|---:|---|---|---|
| 1 | `feat/pertemuan-01-setup` | `pertemuan-01` | Laravel 13 + Livewire 4, MariaDB/Redis, auth dasar, baseline gate |
| 2 | `feat/pertemuan-02-foundation` | `pertemuan-02` | `app/Modules/*`, provider registrasi, route terpisah, 3 layout |
| 3 | `feat/pertemuan-03-database` | `pertemuan-03` | Migrasi seluruh tabel baseline, model, factory, seeder |
| 4 | `feat/pertemuan-04-tenancy` | `pertemuan-04` | `TenantContext`, resolver, global scope, policy, scoped binding, security tests |
| 5 | `feat/pertemuan-05-admin` | `pertemuan-05` | Admin tenant/operator, role, komisi effective-dated, rekening, audit trail |
| 6 | `feat/pertemuan-06-qr-session` | `pertemuan-06` | CRUD meja, QR token opaque + rotasi, anonymous customer session |
| 7 | `feat/pertemuan-07-catalog` | `pertemuan-07` | Katalog/kategori/modifier/stok tenant, public catalog Livewire |
| 8 | `feat/pertemuan-08-cart` | `pertemuan-08` | `CartService` Redis per customer session, revalidasi harga/stok |
| 9 | `feat/pertemuan-09-checkout` | `pertemuan-09` | Checkout atomik multi-tenant, snapshot, idempotency, lock, pre-order |
| 10 | `feat/pertemuan-10-payment` | `pertemuan-10` | Kontrak PaymentGateway, fake/sandbox adapter, attempt, QRIS dinamis |
| 11 | `feat/pertemuan-11-settlement` | `pertemuan-11` | Webhook verified/idempoten, settlement, split ledger, outbox |
| 12 | `feat/pertemuan-12-realtime` | `pertemuan-12` | Private channel KDS, state machine, tracking realtime, notifikasi |
| 13 | `feat/pertemuan-13-reporting` | `pertemuan-13` | Laporan scoped, rekonsiliasi, export async, withdrawal |
| 14 | `feat/pertemuan-14-release` | `pertemuan-14` | Hardening, traceability, security/concurrency/E2E/performance, release rehearsal |

## Tahap per modul (dari modul v7)

### Modul 1 — Persiapan Tool Development & Konfigurasi Lingkungan
Ketertelusuran: Batasan umum §1.3.4; antarmuka §3.1.3; keputusan arsitektur Fase 0.
1. Orientasi artefak & target repository (30')
2. Instal & audit toolchain (45')
3. Buat proyek Laravel 13 (60')
4. Konfigurasi MariaDB, Redis, locale, waktu (75')
5. Instal Livewire/Reverb & validasi proses dev (75')
6. Quality gate & baseline test (45')
7. Dokumentasi & commit pertemuan (30')

### Modul 2 — Fondasi Modular Monolith, Autentikasi, Layout
1. Petakan bounded context (30') · 2. Struktur & provider modul (60') · 3. Route per konteks (60') · 4. Tiga layout (75') · 5. Perluas auth & navigasi (45') · 6. Testing fondasi (60') · 7. Dokumentasi arsitektur & commit (30')

### Modul 3 — Basis Data, Migrasi, Model, Seeder
1. Audit ERD & urutan dependensi (30') · 2. Migrasi tenancy & identitas (60') · 3. Migrasi meja/katalog/komisi (60') · 4. Migrasi order/payment/ledger/outbox (75') · 5. Model/factory/seeder (60') · 6. Verifikasi schema & query (45') · 7. Dokumentasi & commit (30')

### Modul 4 — Isolasi Multi-Tenant: Context, Scope, Policy, Binding
1. Definisikan konteks & aturan akses (30') · 2. Implementasi TenantContext (45') · 3. Middleware resolver & role check (60') · 4. Global scope & model integration (60') · 5. Policy & scoped route binding (60') · 6. Public query & bypass terkontrol (45') · 7. Security tests & commit (60')

### Modul 5 — Administrasi Kantin, Tenant, Role, Skema Komisi
1. Rancang workflow admin (30') · 2. CRUD tenant + Form Request (60') · 3. Penugasan user & role (45') · 4. Komisi effective-dated (75') · 5. Rekening & audit trail (60') · 6. Uji UI & aturan bisnis (60') · 7. Dokumentasi & commit (30')

### Modul 6 — Meja, QR Token Opaque, Sesi Pelanggan Anonim
1. Modelkan lifecycle meja & QR (30') · 2. CRUD meja & authorization (45') · 3. Generator token opaque (60') · 4. Generate/rotate QR (60') · 5. Resolve scan & anonymous session (75') · 6. Security & UX testing (60') · 7. Dokumentasi & commit (30')

### Modul 7 — Katalog Menu Tenant, Modifier, Stok, Public Catalog
1. Aturan katalog (30') · 2. CRUD kategori & menu (60') · 3. Modifier & relasi menu (60') · 4. Stok & toggle habis (45') · 5. Public catalog Livewire (75') · 6. Detail menu & UX modifier (45') · 7. Testing & commit (45')

### Modul 8 — Keranjang Multi-Tenant di Redis & Revalidasi Harga/Stok
1. Schema cart & key (30') · 2. Operasi cart (60') · 3. Validasi modifier & scope canteen (60') · 4. Hitung total & estimasi (45') · 5. Revalidasi sebelum checkout (75') · 6. UI cart Livewire (45') · 7. Test & commit (45')

### Modul 9 — Checkout Atomik, Order Induk, Snapshot, Pre-Order
1. Invariant checkout (30') · 2. Idempotent transaction boundary (45') · 3. Lock & revalidasi inventory (60') · 4. Order & tenant_orders (75') · 5. Item/modifier snapshots (60') · 6. Pre-order & tracking handoff (45') · 7. Concurrency tests & commit (45')

### Modul 10 — Payment Gateway Contract & QRIS Dinamis Sandbox
1. Petakan contract & status (30') · 2. Interface & binding (45') · 3. Create payment & attempt (60') · 4. Fake & sandbox adapter (75') · 5. Halaman QRIS dinamis (60') · 6. Test failure modes (60') · 7. Dokumentasi & commit (30')

### Modul 11 — Webhook Idempoten, Settlement, Split Allocation, Ledger, Outbox
1. Threat model webhook (30') · 2. Verify & normalize event (45') · 3. Persist payment_event idempoten (60') · 4. Atomic settlement & status (60') · 5. Ledger split allocation (75') · 6. Outbox & after-commit worker (45') · 7. Test & commit (45')

### Modul 12 — Kitchen Display System Realtime, Status, Notifikasi
1. Konfigurasi Reverb & Echo (30') · 2. Authorize private channels (45') · 3. State machine tenant order (60') · 4. Bangun KDS Livewire (75') · 5. Tracking pelanggan realtime (45') · 6. Notifikasi idempoten (60') · 7. Test & commit (45')

### Modul 13 — Laporan, Rekonsiliasi, Ekspor, Withdrawal
1. Metrik & sumber (30') · 2. Query laporan tenant-scoped (60') · 3. Rekonsiliasi ledger (45') · 4. Ekspor asynchronous (45') · 5. Request withdrawal + hold (75') · 6. Admin review & disbursement (60') · 7. Test, reconciliation, commit (45')

### Modul 14 — Hardening, E2E, Deployment, Demo
1. Traceability & risk review (30') · 2. Security regression (60') · 3. Concurrency & idempotency (60') · 4. E2E & performance budget (60') · 5. Deployment rehearsal (60') · 6. Dokumentasi operasional & handover (45') · 7. Demo final & retrospective (45')

## Lampiran modul
- Lampiran A — Indeks Source Code
- Lampiran B — Perintah Quality Gate
- Lampiran C — Referensi Resmi
- Lampiran D — Jurnal Refleksi 14 Minggu
- Lampiran E — Catatan Desain Dokumen
- Lampiran F — Audit Penulisan Tutorial
