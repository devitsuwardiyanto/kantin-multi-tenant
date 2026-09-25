# Laporan Implementasi — Aplikasi Kantin Multi-Tenant

Realisasi modul praktikum (Laravel 13 · Livewire 4 · Reverb · MariaDB · Redis) sebagai satu codebase
kumulatif; setiap pertemuan dibekukan sebagai annotated tag + GitHub Release.

- **Repository:** <https://github.com/devitsuwardiyanto/kantin-multi-tenant> (publik)
- **Branch integrasi:** `main` (selalu = pertemuan terakhir yang LULUS). **Rantai v2 (modul vertical slice)** dibangun ulang di
  `rebuild/modular-v2` mulai Pertemuan 2; rantai lama diarsipkan sebagai tag `arsip-v1/pertemuan-NN`, dan tag `pertemuan-NN`
  dipindah setelah seluruh rantai v2 hijau.
- **Workflow:** branch `feat/pertemuan-NN-slug` → PR → CI (`ci`) → merge (rebase) → annotated tag `pertemuan-NN` → Release
- **Dokumen modul revisi:** `Modul_..._v8.docx` (kumulatif dari v7; output nyata disisipkan in-body per modul)
- **Modul per pertemuan:** folder `modul_praktikum/` (`Pendahuluan`, `Pertemuan_NN_*`, `Lampiran`) dipecah otomatis dari master v8
  dan dibuat ulang setiap master berubah; setiap tahap yang membuat berkas memuat blok **BUAT FILE** (perintah Artisan/manual,
  fungsinya, berkas hasil + direktori, output nyata)
- **Pedoman pengembangan:** Laravel Boost (guidelines `CLAUDE.md` + skill `.claude/skills/*`) — diadopsi sejak Pertemuan 6

> Berkas ini diperbarui kumulatif: laporan pertemuan berikutnya ditambahkan di bawah.

## Ringkasan status

| Mg | Judul | Status | Tag | Merge SHA | PR | Test |
|---:|---|---|---|---|---:|---:|
| 1 | Persiapan Tool & Konfigurasi Lingkungan | LULUS | `pertemuan-01` | `e9ce646` | #1 | 33 |
| 2 | Fondasi Modular Monolith, Auth, Layout (v2: vertical slice) | LULUS | `pertemuan-02`* | `80ef839` | #29 | 49 |
| 3 | Basis Data, Migrasi, Model, Seeder | LULUS | `pertemuan-03`* | `46a16a6` | #30 | 55 |
| 4 | Isolasi Multi-Tenant (Context/Scope/Policy/Binding) | LULUS | `pertemuan-04`* | `db0e0ab` | #31 | 65 |
| 5 | Administrasi Kantin, Tenant, Role, Komisi | LULUS | `pertemuan-05`* | `59f46b1` | #32, #33 | 77 |
| 6 | Meja, QR Token Opaque, Sesi Anonim | LULUS | `pertemuan-06`* | `c8de898` | #34 | 85 |
| 7 | Katalog Menu, Modifier, Stok, Public Catalog | LULUS | `pertemuan-07`* | `7bdce0b` | #35 | 93 |
| 8 | Keranjang Redis & Revalidasi Harga/Stok | LULUS | `pertemuan-08`* | `57ac4ca` | #36 | 109 |
| 9 | Checkout Atomik, Order Induk, Snapshot, Pre-Order | LULUS | `pertemuan-09`* | `4f3ae95` | #37 | 120 |
| 10 | Payment Gateway Contract & QRIS Dinamis Sandbox | LULUS | `pertemuan-10`* | `383b50a` | #38 | 127 |
| 11 | Webhook Idempoten, Settlement, Split, Ledger, Reversal | LULUS | `pertemuan-11`* | `db0cee4` | #39, #40 | 139 |
| 12 | Kitchen Display Realtime, Status, Notifikasi | LULUS | `pertemuan-12`* | `32caca8` | #41 | 148 |
| 13 | Laporan, Rekonsiliasi, Ekspor, Withdrawal | LULUS | `pertemuan-13`* | `b101db8` | #42 | 160 |
| 14 | Hardening, Pengujian E2E, Deployment, Demo | LULUS | `pertemuan-14`* | `77b6d06` | #43 | 163 |

\* Rantai v2 di `rebuild/modular-v2`; tag dipindah ke commit ini setelah seluruh rantai hijau (versi lama: `arsip-v1/pertemuan-NN`).
SHA = commit terakhir rentang pertemuan pada `rebuild/modular-v2`.

PR pendukung v1: #9 (integrasi Laravel Boost), #2/#6/#8/#11/#13 (revisi & output in-body DOCX v8).

Toolchain terverifikasi: PHP 8.3.20 · Composer 2.7.1 · Node 22.14 · MariaDB 11.4.5 · Redis 7.2.6 ·
Laravel 13.25.0 · Livewire 4.

Keputusan dependency menunggu persetujuan: **(a)** `chillerlan/php-qrcode` (raster QR cetak, M6) ·
**(b)** `intervention/image` (foto menu WebP, M7).

---

## Pertemuan 01 — Persiapan Tool Development dan Konfigurasi Lingkungan · LULUS

- **GIT:** PR #1 · merge `e9ce646` · tag `pertemuan-01` · Release terbit. `main` di-protect (wajib PR, linear history).
- **OUTPUT:** Laravel 13.25 + starter kit Livewire 4 (Fortify auth, passkeys, 2FA, Flux UI); MariaDB `kantin` + Redis 6379 sehat; halaman login render.
- **TAHAP:** 7/7 (orientasi · audit toolchain · scaffold Laravel 13 · konfig MariaDB/Redis/locale/waktu · Livewire/Reverb · quality gate · dokumentasi).
- **VERIFIKASI:** 33 test (MariaDB `kantin_test`), Pint, PHPStan 0, `npm run build`, `composer validate/audit`/`npm audit` 0 vuln, `git diff --check`; **CI `ci` hijau** (PHP 8.3 + Node 22, service MariaDB 11.4 + Redis 7).
- **KEAMANAN/DATA:** `.env`/`.env.testing` gitignored; repo publik tanpa secret; dev `kantin` kosong sebelum migrasi (schema `test_wbs` tak disentuh).
- **REVISI MODUL:** DOC-01-001 (base v7→v8) · 002 (SRS v2) · 003 (strategi Git main+feat+PR+tag+release) · 004 (DB 3306/kantin, Redis 6379) · 005 (timezone UTC/Asia-Jakarta) · 006 (`install:broadcasting` + echo/pusher + REVERB env).
- **KETERBATASAN:** branch protection butuh repo publik (dipenuhi); render DOCX penuh `TIDAK DIUJI` (tanpa LibreOffice/Word-automation).

## Pertemuan 02 — Fondasi Modular Monolith, Autentikasi, dan Layout · LULUS (v2)

- **GIT:** PR #29 → `rebuild/modular-v2` (commit akhir `80ef839`) · tag `pertemuan-02` dipindah di akhir rebuild (v1: PR #3, `arsip-v1/pertemuan-02`).
- **OUTPUT:** `app/Modules/*` (6 bounded context) sebagai **vertical slice**: basis `App\Modules\ModuleServiceProvider` memuat `routes/*.php`, view `{alias}::`, dan Livewire `{alias}::` dari folder modul; `App\Support\Routing\PortalRoutes` = definisi tunggal grup portal customer/tenant/admin; 3 layout + komponen UI (shared kernel); `modul_praktikum/` per pertemuan.
- **TAHAP:** 7/7 (bounded context · struktur+provider modul · route per konteks · 3 layout · role authz · testing · dokumentasi).
- **VERIFIKASI:** 49 test (148 assertions) termasuk `ModuleConventionTest` (modul tiruan Probe: route/view/Livewire modul, invarian middleware portal); Pint/PHPStan 0/build; CI hijau.
- **KEAMANAN/DATA:** authorization server-side (middleware `role` → 403); middleware portal tak bisa terlupa pada route modul (PortalRoutes); `users.role/status` tidak mass-assignable.
- **REVISI MODUL:** DOC-02-001 (binding ditunda ke M4) · **002** (modul vertical slice + PortalRoutes) · **003** (blok BUAT FILE; jebakan `make:provider` menulis ulang `bootstrap/providers.php`).

## Pertemuan 03 — Implementasi Basis Data, Migrasi, Model, dan Seeder · LULUS (v2)

- **GIT:** PR #30 (commit akhir `46a16a6`) · tag `pertemuan-03` dipindah di akhir rebuild (v1: PR #4).
- **OUTPUT:** 30 tabel baseline via 6 migrasi domain; model inti + factory (shared kernel `app/Models`); seeder 1 canteen/2 tenant idempoten; data dictionary + schema dump — identik v1.
- **TAHAP:** 7/7.
- **VERIFIKASI:** 55 test; `migrate:fresh` + rollback aman; `DemoCanteenSeeder` idempoten; CI hijau.
- **KEAMANAN/DATA:** composite FK anti lintas-tenant (**1452**); CHECK saldo ≥ 0; idempotency UNIQUE; uang integer.
- **REVISI MODUL:** DOC-03-001..003 · **004** (blok BUAT FILE: make:migration + penamaan ulang timestamp, make:model --factory, make:seeder, mariadb-dump).

## Pertemuan 04 — Isolasi Multi-Tenant: Context, Scope, Policy, dan Binding · LULUS (v2)

- **GIT:** PR #31 (commit akhir `db0e0ab`) · tag `pertemuan-04` dipindah di akhir rebuild (v1: PR #5).
- **OUTPUT:** `TenantContext` scoped + resolver `SetTenantContext`; trait `BelongsToTenant`; policy + scoped binding; `PublicCatalogQuery`; **grup tenant (`{tenant:slug}` + `scopeBindings` + `tenant`) di `PortalRoutes::tenant()`** sehingga route modul ikut terlindungi; `TenantMenuController` milik modul Catalog.
- **TAHAP:** 7/7.
- **VERIFIKASI:** 65 test (10 matriks isolasi + invarian portal); CI hijau.
- **KEAMANAN/DATA:** cross-tenant → 404/403; non-anggota (termasuk admin) → 403 pada route modul.
- **REVISI MODUL:** DOC-04-001..002 · **003** (grup tenant di PortalRoutes) · **004** (BUAT FILE; jebakan `make:trait` bernamespace `App\Concerns` → pakai nama lengkap).

## Pertemuan 05 — Administrasi Kantin, Tenant, Role, dan Skema Komisi · LULUS (v2)

- **GIT:** PR #32 + #33 (commit akhir `59f46b1`) · tag `pertemuan-05` dipindah di akhir rebuild (v1: PR #7). Berkas kunci Word `~$…docx` v1 tidak dibawa; `.gitignore` berkas kunci Office.
- **OUTPUT:** controller, Form Request, view (`admin::tenants.*`), dan route administrasi di **modul Admin**; service komisi/rekening/role/audit; guard DB.
- **TAHAP:** 7/7.
- **VERIFIKASI:** 77 test (264 assertions); `CommissionScheduleBoundaryTest` (tepat satu skema di −1 hari/−1 detik/−1 µs/tepat/+1 detik; **gagal 2/3 dengan `subSecond()` lama**); CI hijau (satu rerun karena rate limit 429 unduhan composer).
- **KEAMANAN/DATA:** rekening terenkripsi + last4; guard 1062; **batas komisi interval setengah-terbuka `[valid_from, valid_to)`** — versi lama ditutup tepat di `effectiveAt`, scope `CommissionScheme::effectiveAt()` sebagai definisi tunggal (dipakai checkout M9).
- **REVISI MODUL:** DOC-05-001..003 · **004** (path modul Admin) · **005** (BUAT FILE; jebakan `make:view admin::…`) · **006** (celah ±1 detik pergantian komisi diperbaiki).

## Pertemuan 06 — Meja, QR Token Opaque, dan Sesi Pelanggan Anonim · LULUS (v2)

- **GIT:** PR #34 (commit akhir `c8de898`) · tag `pertemuan-06` dipindah di akhir rebuild (v1: PR #10; Boost v1 PR #9 diputar ulang di rentang ini).
- **OUTPUT:** meja + QR di **modul Admin** (`admin::tables.*`); scan `/q/{token}` + sesi anonim di **modul Ordering** lewat `PortalRoutes::web()`; limiter `qr-scan` didaftarkan `OrderingServiceProvider`.
- **TAHAP:** 7/7.
- **VERIFIKASI:** 85 test; demo live v1 (`/q/<valid>` → 302 + cookie HttpOnly/SameSite; invalid → 404) tetap berlaku; CI hijau.
- **KEAMANAN/DATA:** token CSPRNG ≥128-bit + hash SHA-256; rotasi atomik; 404 generik anti-enumeration; rate limit.
- **REVISI MODUL:** DOC-06-001..003 · **004** (meja di Admin, scan di Ordering) · **005** (BUAT FILE; `make:controller --invokable`).

## Pertemuan 07 — Katalog Menu Tenant, Modifier, Stok, dan Public Catalog · LULUS (v2)

- **GIT:** PR #35 (commit akhir `7bdce0b`) · tag `pertemuan-07` dipindah di akhir rebuild (v1: PR #12).
- **OUTPUT:** komponen Livewire **`catalog::menu-manager`** dan **`catalog::menu-catalog`** (SFC di `app/Modules/Catalog/resources/views/livewire`), halaman `catalog::tenant.menu-manager`, route `tenant.menu-manager` di modul Catalog; `MenuStockService`.
- **TAHAP:** 7/7.
- **VERIFIKASI:** 93 test (331 assertions) termasuk halaman menu-manager merender komponen modul; anti-N+1 (≤6 query); CI hijau.
- **KEAMANAN/DATA:** katalog publik tanpa bocor lintas-canteen; `tenant_id` dari context; `booted()` re-verify membership (tampering → 403); stok atomik + audit.
- **REVISI MODUL:** DOC-07-001..003 · **004** (komponen/halaman/route milik modul Catalog) · **005** (BUAT FILE; `make:livewire catalog::…` langsung menulis ke folder modul).

## Pertemuan 08 — Keranjang Redis per Sesi dan Revalidasi Harga/Stok · LULUS (v2)

- **GIT:** PR #36 (commit akhir `57ac4ca`) · tag `pertemuan-08` dipindah di akhir rebuild (v1: PR #15/#16).
- **OUTPUT:** `CartService` (Redis) + `ResolveCustomerSession` + DTO `CartView`/`CartLine` + `CartException` di modul Ordering; komponen Livewire **`ordering::cart`** (`app/Modules/Ordering/resources/views/livewire/⚡cart.blade.php`) + tombol "Tambah" di `catalog::menu-catalog` (event `cart-add`); beranda pelanggan merender kedua komponen modul. Live v1: tambah 2×Rp16.000 + 1×Rp15.000 → **Subtotal Rp 47.000** (`evidence/pertemuan-08/screenshots/m8-cart.png`).
- **TAHAP:** 7/7 (model penyimpanan Redis per sesi · add/merge + batas · setQuantity/remove/clear · revalidasi harga/stok/ketersediaan · resolver sesi tepercaya · komponen Livewire + wiring katalog · testing & evidence).
- **VERIFIKASI:** 109 test (371 assertions; **+16** — `CartServiceTest` 9, `CartLivewireTest` 5 termasuk beranda merender `catalog::menu-catalog` + `ordering::cart`, `ResolveCustomerSessionTest` 2), **Redis + MariaDB nyata**; PHPStan 0; Pint clean; CI hijau. Bukti Redis: key `cart:<ulid>`, `TTL≈14400s`, subtotal ikut harga DB (20000→26000), `clear` menghapus key.
- **KEAMANAN/DATA:** keranjang di-key per sesi pelanggan (ULID dari cookie HttpOnly ter-hash); Redis hanya identitas + kuantitas — **harga & stok selalu dihitung ulang dari DB**; menu wajib milik tenant AKTIF canteen sesi; modifier lintas-tenant ditolak; hanya `SETEX`/`DEL` satu key milik sesi (**tanpa FLUSHDB**); isolasi antar sesi terbukti.
- **REVISI MODUL:** DOC-08-001..003 · **004** (keranjang di modul Ordering; blok output Modul 8 v1 yang tersisip di judul Modul 9 dipindah ke badan Modul 8) · **005** (BUAT FILE: make:class DTO/service, make:exception FQN, make:livewire ordering::cart).
- **KETERBATASAN:** validasi pivot `menu_modifier_groups` belum di `add()` (validasi level tenant + ketersediaan); checkout menyusul Pertemuan 9. DOCX v8 di `main` (v1) memuat 21 paragraf bersarang akibat bug sisip blok output P8–P14 — diperbaiki di rantai v2 (fungsi repair), Word pada versi v1 mungkin meminta perbaikan dokumen saat dibuka.

## Pertemuan 09 — Checkout Atomik, Order Induk, Snapshot, dan Pre-Order · LULUS (v2)

- **GIT:** PR #37 (commit akhir `4f3ae95`) · tag `pertemuan-09` dipindah di akhir rebuild (v1: PR #17/#18).
- **OUTPUT:** `CheckoutService` (keranjang→order atomik) + DTO `CheckoutResult` + `CheckoutException` di modul Ordering; **`OrderStatusController` + view `ordering::order` + route `customer.order.show`** di `app/Modules/Ordering` (route portal pelanggan milik modul); model `OrderItemModifier`; tombol checkout di `ordering::cart` + cookie pelacakan. Live v1: split 2 tenant, Subtotal Rp 47.000 + Pajak Rp 4.700 + Biaya Rp 940 = **Total Rp 52.640** (`evidence/pertemuan-09/screenshots/m9-order.png`).
- **TAHAP:** 7/7 (revalidasi ulang keranjang · order induk + snapshot · split per-tenant + snapshot komisi · potong stok atomik + idempotensi · pre-order `scheduled_at` · halaman status + pelacakan opaque · testing & evidence).
- **VERIFIKASI:** 120 test (418 assertions; **+11** — `CheckoutServiceTest` 8 termasuk **checkout tepat di batas pergantian komisi**, `OrderCheckoutFlowTest` 3), **Redis + MariaDB nyata**; PHPStan 0; Pint clean; CI hijau. Test batas komisi **gagal** bila `ChangeCommissionSchedule` memakai `subSecond()` lama (dibuktikan lalu dipulihkan).
- **KEAMANAN/DATA:** checkout atomik (rollback tanpa order parsial); revalidasi harga/stok dari DB; idempoten `checkout_key` UNIQUE (+ replay saat balapan); stok berpenjaga (`WHERE stock_qty >= qty`); **komisi effective-dated lewat scope `CommissionScheme::effectiveAt(now())`** — interval setengah-terbuka, tak ada celah di pergantian tarif — di-snapshot (rate + `commission_id`, composite FK); `tenant_id` eksplisit per baris (checkout anonim); token pelacakan opaque (hash SHA-256) via cookie HttpOnly; pengecualian enkripsi `order_tracking` didaftarkan `OrderingServiceProvider`; halaman status 404 generik tanpa token sah.
- **REVISI MODUL:** DOC-09-001..003 · **004** (checkout & status pesanan di modul Ordering; komisi via `effectiveAt()`; blok output v1 dipindah dari judul Modul 10) · **005** (BUAT FILE: make:class DTO/service, make:exception FQN, make:model, make:controller FQN --invokable).
- **KETERBATASAN:** stok dipotong sebagai reservasi saat checkout; pelepasan saat batal/kedaluwarsa menyusul Modul 10–11. Stok modifier belum dipotong.

## Pertemuan 10 — Payment Gateway Contract dan QRIS Dinamis Sandbox · LULUS (v2)

- **GIT:** PR #38 (commit akhir `383b50a`) · tag `pertemuan-10` dipindah di akhir rebuild (v1: PR #19/#20).
- **OUTPUT:** modul **Payments** sebagai vertical slice: kontrak `PaymentGateway` (`make:interface`), DTO `PaymentChargeRequest`/`QrisCharge`, `PaymentException`, adapter `FakeQrisGateway` (EMVCo + CRC16), `PaymentService`, dan komponen Livewire **`payments::order-payment`** yang disematkan halaman `ordering::order`; `ResolveTrackedOrder` (Ordering); model `Payment`/`PaymentAttempt`/`PaymentEvent`. Live v1: "Bayar dengan QRIS" → payload EMVCo dinamis + tombol simulasi sandbox (`evidence/pertemuan-10/screenshots/m10-qris.png`).
- **TAHAP:** 7/7 (kontrak gateway bebas provider · FakeQrisGateway + payload EMVCo/CRC16 · model pembayaran · inisiasi idempoten · konfirmasi sandbox + event append-only · UI QRIS Livewire · testing & evidence).
- **VERIFIKASI:** 127 test (449 assertions; **+7** — `PaymentServiceTest` 4, `FakeQrisGatewayTest` 2 unit, `PaymentLivewireTest` 1; halaman status pesanan kini juga memastikan komponen `payments::order-payment` dirender), **Redis + MariaDB nyata**; PHPStan 0; Pint clean; CI hijau. Container me-resolve `PaymentGateway` → `FakeQrisGateway` (tinker).
- **KEAMANAN/DATA:** **satu provider** di-bind di `PaymentsServiceProvider::register()` (ganti provider = tukar binding tunggal); inisiasi idempoten (satu payment per order); nominal dari `grand_total`; payload EMVCo + CRC16 valid; `payment_event` append-only (dedup `provider_event_id`); simulasi hanya pada gateway sandbox — bukan pengganti webhook produksi.
- **REVISI MODUL:** DOC-10-001..003 · **004** (modul Payments sebagai vertical slice; path diselaraskan; blok output v1 dipindah dari judul Modul 11) · **005** (BUAT FILE: make:interface FQN, make:model ×3, make:class, make:exception FQN, make:livewire payments::order-payment, make:test --unit).
- **KETERBATASAN:** QR raster butuh `chillerlan/php-qrcode` (menunggu persetujuan) — payload ditampilkan sebagai teks. Settlement/ledger + webhook ber-signature menyusul Modul 11.

## Pertemuan 11 — Webhook Idempoten, Settlement, Split Allocation, Ledger, dan Reversal · LULUS (v2)

- **GIT:** PR #39 + #40 (penjelasan CSRF; commit akhir `db0cee4`) · tag `pertemuan-11` dipindah di akhir rebuild (v1: PR #21/#22).
- **OUTPUT:** modul Payments: `WebhookSignatureVerifier` (Support) + `ProcessPaymentWebhook` + `SettlePayment` (settle/reverse) + **`QrisWebhookController` (`app/Modules/Payments/Http/Controllers`) dengan route publik `POST /webhooks/qris` di `app/Modules/Payments/routes/web.php` (`PortalRoutes::web()`)**; model `LedgerEntry`; `confirmSandbox` menjalankan settlement. Live v1 (HTTP nyata): signature valid → 200 + settlement; replay → duplicate; signature salah → 401; saldo `available=34000` (`evidence/pertemuan-11/screenshots/m11-webhook.png`).
- **TAHAP:** 7/7 (verifikasi signature raw body · dedup idempoten event · event + lunas + settlement atomik · split allocation per tenant · ledger append-only + saldo · reversal · testing & evidence).
- **VERIFIKASI:** 139 test (489 assertions; **+12** — `WebhookSettlementTest` 6 termasuk pengecualian CSRF modul, `SettlePaymentTest` 3, `WebhookSignatureVerifierTest` 2 unit, + uji pengecualian enkripsi cookie `order_tracking`), **Redis + MariaDB nyata**; PHPStan 0; Pint clean; CI hijau. Uji pengecualian CSRF **gagal** bila baris `PreventRequestForgery::except('webhooks/*')` di provider dinonaktifkan (dibuktikan, lalu dipulihkan).
- **KEAMANAN/DATA:** HMAC-SHA256 atas **raw body** + `hash_equals`, fail-closed; dedup `provider_event_id` UNIQUE + `ledger_entries.idempotency_key` UNIQUE; event+lunas+settlement satu transaksi; split per tenant (`sale_credit` / `commission_debit`); ledger append-only + reversal (CHECK saldo non-negatif); **pengecualian CSRF `webhooks/*` didaftarkan `PaymentsServiceProvider::boot()`** (bukan `bootstrap/app.php`) — keaslian via signature; secret webhook dari `.env` (tidak di-commit).
- **REVISI MODUL:** DOC-11-001..003 · **004** (webhook & pengecualian CSRF milik modul Payments; kalimat konsep CSRF diselaraskan; path diselaraskan; blok output v1 dipindah dari judul Modul 12) · **005** (BUAT FILE: make:class verifier/service, make:controller FQN --invokable, make:model LedgerEntry, make:test --unit) · **006** (subbagian "Mengapa Endpoint Webhook Dikecualikan dari CSRF?" + Output Nyata M11c: POST tanpa token → 419, webhook → 401 oleh signature, pengecualian dicabut → webhook 419).
- **KETERBATASAN:** settlement kredit langsung ke `available` (siklus hold/release = penyempurnaan lanjutan); reversal gagal-tertutup bila dana telah ditarik; rate limit webhook ditambahkan pada hardening (Modul 14). Withdrawal = Modul 13.

## Pertemuan 12 — Kitchen Display System Realtime, Status, dan Notifikasi · LULUS (v2)

- **GIT:** PR #41 (commit akhir `32caca8`) · tag `pertemuan-12` dipindah di akhir rebuild (v1: PR #23/#24).
- **OUTPUT:** modul **Kitchen** sebagai vertical slice: `KitchenService` (mesin status) + `KitchenException`; event `TenantOrderStatusChanged`/`NewTenantOrderReceived` di `app/Modules/Kitchen/Events`; `TenantChannels` di `app/Modules/Kitchen/Realtime`; **channel privat `tenant.{tenantId}.orders` didaftarkan `KitchenServiceProvider::boot()`**; komponen **`kitchen::kitchen-board`** + halaman `kitchen::tenant.kitchen-board` + route `tenant.kitchen` di `app/Modules/Kitchen/routes/tenant.php`. `SettlePayment` (Payments) memicu `NewTenantOrderReceived` — komunikasi lintas modul lewat event. Live v1: papan KDS kolom Baru/Diterima/Disiapkan/Siap (`evidence/pertemuan-12/screenshots/m12-kitchen.png`).
- **TAHAP:** 7/7 (mesin status + validasi transisi · event broadcast + channel privat · otorisasi keanggotaan channel · komponen KDS Livewire (booted re-verify) · siaran order baru saat settlement · listener Echo + wire:poll fallback · testing & evidence).
- **VERIFIKASI:** 148 test (515 assertions; **+9** — `KitchenServiceTest` 3, `TenantChannelsTest` 2 termasuk channel terdaftar oleh modul, `KitchenBoardLivewireTest` 4 termasuk halaman KDS merender komponen modul), **Redis + MariaDB nyata**; PHPStan 0; Pint clean; CI hijau.
- **KEAMANAN/DATA:** state machine dengan transisi tak sah ditolak; channel privat hanya untuk anggota tenant (didaftarkan di provider agar tetap aktif saat `route:cache`); KDS `booted()` re-verify membership + `TenantContext`; `NewTenantOrderReceived` `ShouldDispatchAfterCommit`; perubahan status ter-audit.
- **REVISI MODUL:** DOC-12-001..003 · **004** (KDS milik modul Kitchen; channel via provider; nama channel `.kds` → `.orders`; path diselaraskan; blok output v1 dipindah dari judul Modul 13) · **005** (BUAT FILE: make:class, make:exception FQN, make:livewire kitchen::kitchen-board, make:event FQN ×2, make:test).
- **KETERBATASAN:** koneksi Reverb live butuh server Reverb (realtime diverifikasi lewat dispatch event + otorisasi channel, bukan WebSocket end-to-end di CI); siklus hold→release terikat penyelesaian pesanan = penyempurnaan lanjutan.

## Pertemuan 13 — Laporan, Rekonsiliasi, Ekspor, dan Withdrawal · LULUS (v2)

- **GIT:** PR #42 (commit akhir `b101db8`) · tag `pertemuan-13` dipindah di akhir rebuild (v1: PR #25/#26).
- **OUTPUT:** modul **Reporting**: `TenantLedgerReport` (summary/reconcile/rows), `TenantLedgerExportController` (CSV) di `app/Modules/Reporting/Http/Controllers`, komponen **`reporting::finance-panel`** + halaman + route `tenant.finance`/`tenant.finance.export`; modul **Payments**: `WithdrawalService` + `WithdrawalException`, komponen **`payments::withdrawal-review`** + halaman + route `admin.withdrawals.index`. Live v1: panel keuangan — Saldo Rp 42.500, Penjualan Kotor Rp 50.000, Komisi Rp 7.500, Rekonsiliasi "cocok" (`evidence/pertemuan-13/screenshots/m13-finance.png`).
- **TAHAP:** 7/7 (laporan dari ledger · rekonsiliasi saldo vs ledger · ekspor CSV · withdrawal hold · approve/reject + satu aktif per tenant · UI tenant/admin · testing & evidence).
- **VERIFIKASI:** 160 test (567 assertions; **+12** — `WithdrawalServiceTest` 5, `TenantLedgerReportTest` 2, `FinanceFlowTest` 5 termasuk kedua halaman merender komponen modulnya), **Redis + MariaDB nyata**; PHPStan 0; Pint clean; CI hijau.
- **KEAMANAN/DATA:** withdrawal atomik berbasis ledger (hold → withdrawal_debit / release), satu aktif per tenant (UNIQUE `active_tenant_lock`), validasi nominal/saldo/rekening verified; CHECK saldo non-negatif; rekonsiliasi saldo materialisasi == akumulasi ledger; ekspor CSV ter-scope TenantContext; `finance-panel` re-verify membership (403); `withdrawal-review` hanya kantin yang dikelola (lintas kantin 403).
- **REVISI MODUL:** DOC-13-001..003 · **004** (keuangan di modul Reporting, penarikan di modul Payments; path termasuk `app/Queries/...` diselaraskan; blok output v1 dipindah dari judul Modul 14) · **005** (BUAT FILE: make:class, make:controller FQN --invokable, make:exception FQN, make:livewire reporting::finance-panel & payments::withdrawal-review, make:test).
- **KETERBATASAN:** reject/refund gagal-tertutup bila dana telah ditarik; transfer bank nyata (payout provider) di luar lingkup.

## Pertemuan 14 — Hardening, Pengujian End-to-End, Deployment, dan Demo · LULUS (v2, PENUTUP)

- **GIT:** PR #43 → `rebuild/modular-v2` · tag `pertemuan-14` dipindah di akhir rebuild (v1: PR #27/#28).
- **OUTPUT:** `SecurityHeaders` middleware global (`bootstrap/app.php`, shared kernel); limiter **`qris-webhook` didaftarkan `PaymentsServiceProvider`** dan dipasang pada route webhook modul Payments; `EndToEndJourneyTest` (capstone lintas modul); `docs/DEPLOYMENT.md` (+ catatan `route:cache` untuk route modul & channel via provider) + `docs/DEMO.md`. Capstone v2: `evidence/pertemuan-14/screenshots/m14-capstone.png`.
- **TAHAP:** 7/7 (audit keamanan · header keamanan global · rate limit webhook · uji E2E capstone · checklist deployment · skrip demo · finalisasi dokumentasi).
- **VERIFIKASI:** 163 test (593 assertions; **+3** — `EndToEndJourneyTest` 1, `SecurityHeadersTest` 1, uji limiter webhook modul Payments 1), **Redis + MariaDB nyata**; PHPStan 0; Pint clean; CI hijau. **`php artisan route:cache` diverifikasi**: route semua modul (termasuk closure) masuk cache, channel `tenant.{tenantId}.orders` tetap aktif; cache dibersihkan kembali.
- **KEAMANAN/DATA:** header keamanan global terbukti via HTTP nyata (`curl -sI`); rate limit webhook melengkapi signature HMAC + dedup; kontrol tertanam: isolasi tenant berlapis, uang integer, ledger append-only + reversal, CHECK non-negatif, idempotensi berlapis, cookie HttpOnly/Secure-prod, `prohibitDestructiveCommands`, tanpa FLUSHDB.
- **REVISI MODUL:** DOC-14-001..003 · **004** (hardening milik modul; `route:cache` terverifikasi; gambar capstone v2; blok output v1 yang tersisip di judul Lampiran A dipindah ke badan Modul 14) · **005** (BUAT FILE: make:middleware, make:test, dokumen/evidence manual).
- **KETERBATASAN:** DAST/pentest & uji beban di luar lingkup; CSP ketat belum dipasang; Reverb/queue live perlu proses berjalan di produksi.

**Penutup (v2).** Pertemuan 1–14 kini tersedia sebagai rantai kumulatif **vertical slice** di `rebuild/modular-v2` (PR #29–#43, CI hijau di setiap PR), dengan DOCX v8 dan `modul_praktikum/` per pertemuan yang memuat blok BUAT FILE dan output nyata in-body. Langkah tersisa: memindahkan tag `pertemuan-02…14` ke commit rantai v2 dan menjadikan `main` = rantai v2 (menunggu persetujuan pemilik; rantai lama tetap di `arsip-v1/pertemuan-NN`).

---

## Integrasi Laravel Boost (PR #9)

- `laravel/boost ^2.5` (require-dev) + `CLAUDE.md` (guidelines) + `.claude/skills/*` (Livewire, Flux UI, Fortify, Echo, Tailwind, Laravel best practices, infer-conventions) + `.mcp.json` + `boost.json`.
- Diadopsi sejak Pertemuan 6: `php artisan make:*` untuk scaffold, PHPUnit (`make:test --phpunit`, `--filter`/`--compact`), `pint --dirty --format agent`, konvensi PHP 8 (curly braces, constructor promotion, return types, PHPDoc array shape), route bernama, aktivasi skill domain, test enforcement.
- Catatan: task ini secara eksplisit meminta evidence/ADR/docs, sehingga tetap dibuat meski Boost default konservatif soal dokumentasi. Boost MCP tools (search-docs, database-*) tidak ter-registrasi pada sesi ini; guidelines diikuti via `CLAUDE.md` + skill.

---

## Revisi v2 — Modul sebagai Vertical Slice (2026-09-23) · DIKERJAKAN BERTAHAP

- **STATUS:** SELESAI — Pertemuan 2–14 dibangun ulang dan LULUS di `rebuild/modular-v2`; tag `pertemuan-02…14` dipindah dan `main` = `77b6d06` (24 September 2026).
- **GIT:** tag arsip `arsip-v1/pertemuan-01…14` (rantai lama utuh); branch integrasi `rebuild/modular-v2` dari akhir Pertemuan 1 (`87d0de7`); PR #29–#43 per pertemuan (rebase merge, CI hijau). Satu kali force-push (with-lease, atas persetujuan pemilik) ke `rebuild/modular-v2` untuk menyisipkan blok BUAT FILE Modul 2–4 ke rentang pertemuannya.
- **OUTPUT:** setiap modul memiliki `Http/Controllers`, `Http/Requests`, `routes/{portal}.php`, `resources/views` (+ `livewire/`) sendiri; model, policy, middleware, layout, dan komponen UI tetap shared kernel; `modul_praktikum/` per pertemuan.
- **TAHAP:** per pertemuan: putar ulang commit v1 → pindahkan berkas fitur ke modul pemilik → quality gate → revisi DOCX in-body (path, Output Nyata b, BUAT FILE) → pecah ulang `modul_praktikum/` → PR → CI → merge.
- **PERUBAHAN:** `ModuleServiceProvider`, `PortalRoutes` (`customer/tenant/admin/web`), limiter & konfigurasi middleware milik modul (`EncryptCookies::except` + `qr-scan` di Ordering, `PreventRequestForgery::except` + `qris-webhook` di Payments, channel broadcast di Kitchen), scope `CommissionScheme::effectiveAt()`, `ModuleConventionTest`, `CommissionScheduleBoundaryTest`.
- **VERIFIKASI:** tiap pertemuan Pint + PHPStan 0 + suite penuh di MariaDB nyata + CI; perintah BUAT FILE dijalankan di worktree bersih dan berkas hasilnya dicocokkan dengan berkas baru pertemuan (selisih hanya screenshot evidence / `mariadb-dump`).
- **KEAMANAN/DATA:** tak ada `.env`/secret di Git; worktree verifikasi memakai salinan `.env` di scratchpad (di luar repo); tidak ada FLUSHDB/penghapusan global; tidak ada dependency baru.
- **REVISI MODUL:** DOC-02-002/003, DOC-03-004, DOC-04-003/004, DOC-05-004/005/006, DOC-06-004/005, DOC-07-004/005, DOC-08-004/005, DOC-09-004/005, DOC-10-004/005, DOC-11-004/005/006, DOC-12-004/005, DOC-13-004/005, DOC-14-004/005.
- **TEMUAN:** DOCX v8 v1 (commit docs P8–P14) menyisipkan blok Output Nyata DI DALAM paragraf judul modul berikutnya (21 paragraf bersarang di `main`; skema OOXML tidak valid). Rantai v2 memperbaikinya dengan fungsi `repair` (idempoten, konten utuh) — blok dipindah ke badan modul pemiliknya sebelum "Observe".
- **KETERBATASAN:** render DOCX penuh per halaman `TIDAK DIUJI` (tanpa LibreOffice/Word-automation; struktur XML, idempotensi patch, dan pratinjau Quick Look/`textutil` diverifikasi).
- **BERIKUTNYA:** pemindahan tag `pertemuan-02…14` ke commit rantai v2, `main` = ujung `rebuild/modular-v2` (force-push, perlu persetujuan eksplisit), dan pembaruan GitHub Release.


---

## Rantai v3 — Use Case SRS per Pertemuan (2026-09-24) · SELESAI

- **STATUS:** mulai Pertemuan 5 setiap pertemuan menuntaskan minimal satu use case SRS v2 (23 UC, tuntas pada Pertemuan 14). Pertemuan 5–14 LULUS: 23 dari 23 UC tuntas (P5–P13) dan lulus uji penerimaan browser 23/23 (P14).
- **GIT:** branch integrasi `rebuild/usecase-v3`; rentang v2 Pertemuan 5–14 diarsipkan sebagai tag `arsip-v2/pertemuan-05…14`; satu PR per pertemuan (rebase merge, CI hijau): P5 #44 (+ #45 langkah Windows, #46 tema kode, #47 aturan, #48–#51 konversi Modul 1–4), P6 #52, P7 #53, P8 #54, P9 #55, P10 #56, P11 #57, P12 #58, P13 #59, P14 PR ini. `main` dan tag `pertemuan-NN` dipindah setelah rantai hijau.
- **OUTPUT:** setiap modul DOCX memuat bagian **Use Case SRS yang Dituntaskan** (deskripsi SRS + mockup `#uc-NN`), **Tahap 8** penuntasan use case, dan **Output Nyata** dari browser; seluruh langkah praktikum ditulis ulang untuk Windows (cmd + VS Code, ServBay) dengan isi berkas lengkap dan tema kode Cyan Light.
- **TAHAP:** per pertemuan: putar ulang kode v2 → implementasi use case (Tahap 8) → uji otomatis + uji browser → revisi DOCX (UC, langkah Windows, tema) → verifikasi langkah manual di worktree bersih → `modul_praktikum/` → PR → CI → merge.
- **PERUBAHAN:**
  - **P5 — UC-19 Autentikasi** (lockout 5x/10 menit, pesan generik, redirect per peran, sesi 8 jam) + **UC-21 Kelola Tenant & Komisi** (onboarding owner + rekening, aktif/nonaktif, riwayat komisi); 93 test.
  - **P6 — UC-22 Kelola Meja & QR** (QR SVG siap unduh/cetak via `bacon/bacon-qr-code`, nonaktif/aktif meja) + **UC-02 Pindai QR** (sambutan meja, tenant buka/tutup, pesan QR tidak valid, tolak di luar jam operasional WIB); 109 test.
  - **P7 — UC-13 Kelola Menu & Stok** (ubah, hapus lunak, deskripsi, foto WebP maks 800 px via GD, cari/saring kategori) + **UC-14 Tandai Habis** (sakelar satu klik + audit) + **UC-01 Telusuri** (menu per tenant, menu habis tampil nonaktif, tenant tutup + jam buka, chip kategori, poll 5 detik) + **UC-11 Estimasi Waktu Tunggu** (antrean `accepted`/`preparing` ÷ kapasitas paralel 2 + waktu siap, rentang “± a–b mnt”); 124 test.
  - **P8 — UC-03 Kelola Keranjang** (kelompok per tenant + subtotal, pajak dan biaya layanan per tenant dari tarif kantin — rumus sama dengan checkout, “Menu telah habis”, checkout nonaktif saat kosong) + **UC-04 Kustomisasi Item** (`ordering::item-customizer`: grup wajib/opsional min/maks, opsi habis nonaktif, catatan ≤ 200 karakter tanpa tag HTML, harga akhir = dasar + modifier; validasi ulang di `CartService`) + halaman tenant `catalog::modifier-manager`; 155 test. Uji browser menemukan bug checkbox Livewire (nilai awal harus array) → diperbaiki + test regresi.
  - **P9 — UC-05 Checkout Pesanan** (halaman `ordering::checkout`: ringkasan per tenant, nomor meja, mode penyajian makan di tempat / pesan dulu, nama + WhatsApp dinormalisasi 62…, tenant wajib buka, item bermasalah → kembali ke keranjang tanpa pesanan parsial, catatan item tersimpan di `order_items.note`) + **UC-06 Jadwalkan Pre-Order** (`PreOrderScheduler`: slot 15 menit ≥ 15 menit dari sekarang, jam operasional semua tenant, pre-order aktif, kapasitas per slot dicek ulang di transaksi dengan baris tenant terkunci, ditolak → dua slot terdekat; status `scheduled` + `release_at`; `ordering:release-scheduled` tiap menit melepas pesanan yang sudah dibayar) + halaman tenant `ordering::pre-order-settings`; 179 test. Test menemukan bug zona waktu (Carbon WIB disimpan tanpa konversi) → diperbaiki.
  - **P10 — UC-07 Bayar via QRIS Dinamis** (QRIS dibangkitkan otomatis setelah checkout dan tampil di atas halaman pesanan sebagai gambar SVG via bacon — menutup penundaan QR raster v2 tanpa dependency baru; NMID tersamar, penghitung mundur ≤ 15 menit, poll status 3 detik; `PaymentChargeRequest` membawa split per tenant; gateway dipanggil di luar transaksi dengan maksimal 3 percobaan → pesan gangguan; QRIS kedaluwarsa → Buat QRIS baru; tombol Batalkan pesanan mengembalikan stok); 193 test. Uji browser menemukan pesan gangguan yang hilang saat poll → diperbaiki.
  - **P11 — UC-08 Verifikasi Pembayaran** (signature salah → 401 + audit; nominal wajib dan dicocokkan → tinjau manual; lock payment + order, dibayar tepat satu kali; expire/deny → pesanan batal + stok kembali; pembayaran setelah pesanan dibatalkan → tinjau; event `PaymentVerified` setelah commit; layar “Pembayaran terverifikasi”) + **UC-10 Split Payment Otomatis** (kredit pengelola kantin di `platform_ledger_entries`, rincian `payment_allocations`, invarian selisih 0, pembulatan ke pengelola + log, rollback + `payments:reprocess-settlements`); 217 test. Uji browser memakai webhook `curl` + HMAC.
  - **P12 — UC-15 Proses Antrean Dapur (KDS)** (kolom Baru/Diproses/Siap sesuai mockup, Terima → Selesai → Diserahkan, batal wajib beralasan → `cancel_reason` + `refund_status` pending, penanda suara dengan sakelar, indikator koneksi WebSocket + polling cadangan 10 dtk, pre-order masuk dapur saat dilepas) + **UC-09 Lacak Status Pesanan** (`ordering::order-tracker`: tahapan per tenant + estimasi UC-11, channel privat `order.{public_id}` diotorisasi sesi anonim + token pelacakan, event tanpa ID internal, polling 15 dtk) + **UC-12 Kirim Notifikasi Status** (kontrak `NotificationGateway` + `FakeWhatsAppGateway`, job antrean 3 percobaan dengan jeda bertingkat, `notification_deliveries` idempoten, kegagalan tidak menggagalkan pesanan, pengingat pre-order UC-06 langkah 6); diuji dengan Reverb nyata; 241 test.
  - **P13 — UC-16 Lihat Laporan Penjualan** (`reporting::sales-report`: omset, transaksi, rata-rata, median, perbandingan periode lalu, menu terlaris, jam sibuk WIB, rentang tanggal + preset, keadaan kosong) + **UC-17 Ekspor Laporan** (.xlsx ditulis manual dengan ZipArchive dan PDF via `dompdf/dompdf` — dependency disetujui pengguna — dibuat di antrean, tautan bertanda tangan 24 jam, surel untuk volume besar, berkas kedaluwarsa dihapus tiap jam) + **UC-18 Rekonsiliasi** (ledger per pembayaran + referensi, telusur pesanan, pembayaran perlu peninjauan tidak dihitung, saldo dibandingkan Σ ledger) + **UC-20 Ajukan Penarikan** (kunci baris saldo, minimum Rp100.000, satu pengajuan aktif, ledger `hold`, notifikasi pengelola) + **UC-23 Verifikasi Pencairan** (bukti transfer wajib di disk privat, alasan penolakan → `release`, saldo ≠ Σ ledger → investigasi, persetujuan idempoten); 284 test.
  - **P14 — Uji penerimaan 23 use case** di browser pada basis data demo baru (web + queue worker + Reverb nyata, webhook QRIS ber-HMAC; tiga sesi peramban: pengelola, tenant, pelanggan) → **23/23 PASS**, hasil dicatat di RTM. Temuan: rekening hasil onboarding (UC-21) tidak dapat diverifikasi lewat antarmuka sehingga prasyarat UC-20 tak terpenuhi → tombol Verifikasi/Tolak/Jadikan utama + `BankAccountVerificationTest`. `RequirementsTraceabilityTest` menjadikan RTM artefak teruji (use case hilang, belum PASS, atau test rujukan hilang → gagal; diuji dengan mutasi). Hardening v2 (SecurityHeaders, limiter `qris-webhook` milik Payments, E2E capstone yang diselaraskan dengan aturan v3), skrip demo mengikuti alur v3. **Audit per langkah SRS** setelah uji penerimaan menutup empat celah: siaran WebSocket perubahan katalog (UC-14 langkah 3; HABIS tampil ± 1 detik), layar pengelola “Perlu Tindak Lanjut” untuk pembayaran perlu ditinjau (UC-08 alur 3a) dan pengembalian dana dari dapur dengan entri reversal ledger (UC-15 alur 4a), serta uji konkurensi dua proses PHP paralel (mutu UC-20/UC-23) — yang menemukan deadlock MariaDB pada pengajuan bersamaan, kini diulang otomatis; 304 test.
- **VERIFIKASI:** per pertemuan Pint + PHPStan 0 + suite penuh di MariaDB nyata dengan cache/sesi array **dan** Redis + CI; uji browser (puppeteer + Chrome) pada server demo; langkah manual Windows dijalankan ulang di worktree bersih (cmd diterjemahkan) — hasil Tahap 7 identik dengan kode v2 dan hasil Tahap 8 identik dengan kode akhir (P7: 117 → 124 test; P8: 140 → 155 test; P9: 166 → 179 test; P10: 186 → 193 test; P11: 205 → 217 test; P12: 226 → 241 test; P13: 253 → 284 test; P14: 287 → 304 test).
- **KEAMANAN/DATA:** `tenant_id` hanya dari TenantContext/keanggotaan; komponen Livewire memverifikasi ulang keanggotaan di `booted()`; foto disimpan dengan nama UUID per tenant; menu dihapus lunak agar riwayat pesanan tetap utuh; tanpa `.env`/secret di Git; tanpa FLUSHDB.
- **REVISI MODUL:** DOC-00-001 (tema Cyan Light) · DOC-01-007 · DOC-02-004 · DOC-03-005 · DOC-04-005 · DOC-05-007/008 · DOC-06-006/007 · DOC-07-006/007 (DOC-07-001 foto menu selesai tanpa dependency baru) · DOC-08-006/007 · DOC-09-006/007 · DOC-10-006/007 · DOC-11-007/008 · DOC-12-006/007 · DOC-13-006/007 · DOC-14-006/007/008 · DOC-00-002 (contoh folder ServBay).
- **KETERBATASAN:** gateway WhatsApp masih sandbox (`FakeWhatsAppGateway` menulis ke log; provider nyata belum dipilih) dan Web Push belum diimplementasikan — SRS mengizinkan “Web Push dan/atau WhatsApp”; pengembalian dana dicatat di sistem (reversal ledger) tetapi uangnya diserahkan ke pelanggan di luar sistem (tunai/transfer) — refund otomatis lewat gateway belum ada; kebutuhan mutu berupa waktu (mis. katalog 500 item ≤ 2 detik) belum diukur secara formal; render DOCX penuh per halaman `TIDAK DIUJI` (tanpa LibreOffice/Word).
- **BERIKUTNYA:** pemindahan tag `pertemuan-05…14` ke commit rantai v3 dan `main` = ujung `rebuild/usecase-v3` (force-push `main` dijalankan pemilik repositori), lalu pembaruan catatan Release.
