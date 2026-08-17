# Laporan Implementasi — Aplikasi Kantin Multi-Tenant

Realisasi modul praktikum (Laravel 13 · Livewire 4 · Reverb · MariaDB · Redis) sebagai satu codebase
kumulatif; setiap pertemuan dibekukan sebagai annotated tag + GitHub Release.

- **Repository:** <https://github.com/devitsuwardiyanto/kantin-multi-tenant> (publik)
- **Branch integrasi:** `main` (selalu = pertemuan terakhir yang LULUS)
- **Workflow:** branch `feat/pertemuan-NN-slug` → PR → CI (`ci`) → merge (rebase) → annotated tag `pertemuan-NN` → Release
- **Dokumen modul revisi:** `Modul_..._v8.docx` (kumulatif dari v7; output nyata disisipkan in-body per modul)
- **Pedoman pengembangan:** Laravel Boost (guidelines `CLAUDE.md` + skill `.claude/skills/*`) — diadopsi sejak Pertemuan 6

> Berkas ini diperbarui kumulatif: laporan pertemuan berikutnya ditambahkan di bawah.

## Ringkasan status

| Mg | Judul | Status | Tag | Merge SHA | PR | Test |
|---:|---|---|---|---|---:|---:|
| 1 | Persiapan Tool & Konfigurasi Lingkungan | LULUS | `pertemuan-01` | `e9ce646` | #1 | 33 |
| 2 | Fondasi Modular Monolith, Auth, Layout | LULUS | `pertemuan-02` | `735fad4` | #3 | 44 |
| 3 | Basis Data, Migrasi, Model, Seeder | LULUS | `pertemuan-03` | `9b6f4f6` | #4 | 50 |
| 4 | Isolasi Multi-Tenant (Context/Scope/Policy/Binding) | LULUS | `pertemuan-04` | `fc37000` | #5 | 60 |
| 5 | Administrasi Kantin, Tenant, Role, Komisi | LULUS | `pertemuan-05` | `6d17ab8` | #7 | 69 |
| 6 | Meja, QR Token Opaque, Sesi Anonim | LULUS | `pertemuan-06` | `5e12aa9` | #10 | 77 |
| 7 | Katalog Menu, Modifier, Stok, Public Catalog | LULUS | `pertemuan-07` | `7bae344` | #12 | 84 |
| 8 | Keranjang Redis & Revalidasi Harga/Stok | LULUS | `pertemuan-08` | `0ef56b2` | #15 | 99 |
| 9 | Checkout Atomik, Order Induk, Snapshot, Pre-Order | LULUS | `pertemuan-09` | `b16743c` | #17 | 109 |
| 10 | Payment Gateway Contract & QRIS Dinamis Sandbox | LULUS | `pertemuan-10` | `e32e71c` | #19 | 116 |
| 11 | Webhook Idempoten, Settlement, Split, Ledger, Reversal | LULUS | `pertemuan-11` | `1c1e353` | #21 | 126 |
| 12 | Kitchen Display Realtime, Status, Notifikasi | LULUS | `pertemuan-12` | `dbd632f` | #23 | 133 |
| 13–14 | (Laporan/Withdrawal … Rilis) | BELUM | — | — | — | — |

PR pendukung: #9 (integrasi Laravel Boost), #2/#6/#8/#11/#13/#16 (revisi & output in-body DOCX v8).

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

## Pertemuan 02 — Fondasi Modular Monolith, Autentikasi, dan Layout · LULUS

- **GIT:** PR #3 · merge `735fad4` · tag `pertemuan-02` · Release.
- **OUTPUT:** `app/Modules/*` (6 bounded context: Admin/Catalog/Ordering/Payments/Kitchen/Reporting) + provider; route publik/tenant/admin bernama; 3 layout (customer/tenant/admin) + komponen `x-button/input/status-badge/empty-state`.
- **TAHAP:** 7/7 (bounded context · struktur+provider · route per konteks · 3 layout · role authz · testing · dokumentasi).
- **VERIFIKASI:** 44 test; `route:list` tanpa nama duplikat; Pint/PHPStan 0/build; CI hijau. Regresi 33 test M1 hijau.
- **KEAMANAN/DATA:** authorization server-side (middleware `role` → 403); `users.role/status` **tidak** mass-assignable; guest → login.
- **REVISI MODUL:** DOC-02-001 (model binding `{tenant:slug}`/`scopeBindings` ditunda ke Modul 4).

## Pertemuan 03 — Implementasi Basis Data, Migrasi, Model, dan Seeder · LULUS

- **GIT:** PR #4 · merge `9b6f4f6` · tag `pertemuan-03` · Release.
- **OUTPUT:** 30 tabel baseline (ERD Fase 0) via 6 migrasi domain; 15 model inti + factory; seeder 1 canteen/2 tenant idempoten; data dictionary + schema dump.
- **TAHAP:** 7/7 (audit ERD · tenancy · katalog/komisi · order/payment/ledger/outbox · model/factory/seeder · verifikasi schema · dokumentasi).
- **VERIFIKASI:** 50 test (MariaDB nyata); `migrate:fresh` + **rollback aman**; CI hijau.
- **KEAMANAN/DATA:** composite FK `(tenant_id, x_id)` menolak relasi lintas-tenant (**1452**); `orders` tanpa `tenant_id` (1 order → banyak `tenant_orders`); token `varbinary(32)`; CHECK saldo≥0; append-only ledger/event/audit; idempotency UNIQUE; uang integer.
- **REVISI MODUL:** DOC-03-001 (SQL baseline `00_database` tak disertakan → skema dari ERD) · 002 (binary→varbinary) · 003 (penomoran migrasi).

## Pertemuan 04 — Isolasi Multi-Tenant: Context, Scope, Policy, dan Binding · LULUS

- **GIT:** PR #5 · merge `fc37000` · tag `pertemuan-04` · Release.
- **OUTPUT:** `TenantContext` request/job-scoped + resolver `SetTenantContext`; trait `BelongsToTenant` (global scope + auto-fill) pada 8 model; `MenuPolicy`/`TenantOrderPolicy`/`WithdrawalPolicy` + scoped binding; `PublicCatalogQuery` (bypass terkontrol); job tenant-scoped.
- **TAHAP:** 7/7 (konteks & aturan · TenantContext · resolver+role · global scope · policy+binding · public bypass · security tests).
- **VERIFIKASI:** 60 test (10 matriks isolasi); CI hijau.
- **KEAMANAN/DATA (matriks allow/deny, MariaDB nyata):** global scope hanya tenant aktif · scoped-binding cross-tenant → **404** · policy update silang → ditolak · public catalog tanpa bocor canteen/suspended · job context init/clear per tenant · suspended/non-anggota → **403**. Composite FK tetap benteng (1452).
- **REVISI MODUL:** DOC-04-001 (resolver membership menggantikan `role:tenant` coarse; tes M2 disesuaikan) · 002 (scope lenient tanpa context + mitigasi).

## Pertemuan 05 — Administrasi Kantin, Tenant, Role, dan Skema Komisi · LULUS

- **GIT:** PR #7 · merge `6d17ab8` · tag `pertemuan-05` · Release.
- **OUTPUT:** halaman admin tenant/operator; service komisi atomik; workflow rekening pending→verified/rejected; audit trail.
- **TAHAP:** 7/7 (workflow admin · CRUD tenant+Form Request · role assignment · komisi effective-dated · rekening+audit · uji UI/aturan · dokumentasi).
- **VERIFIKASI:** 69 test; CI hijau.
- **KEAMANAN/DATA:** policy-centric authz (non-manager 403); canteen dari konteks tepercaya; nomor rekening **terenkripsi** + last4 (bukan plaintext di DB/log/audit); overlap komisi & primary ganda → **1062** (guard DB generated-column+UNIQUE); owner terakhir dilindungi; audit append-only tersanitasi.
- **REVISI MODUL:** DOC-05-001 (anti-overlap via generated column+UNIQUE) · 002 (CarbonInterface) · 003 (primary guard).

## Pertemuan 06 — Meja, QR Token Opaque, dan Sesi Pelanggan Anonim · LULUS

- **GIT:** PR #10 · merge `5e12aa9` · tag `pertemuan-06` · Release. (Boost diintegrasikan via PR #9.)
- **OUTPUT:** CRUD meja + generate/rotate QR (token opaque); resolver `/q/{token}`; `CustomerSession` anonim + cookie aman; landing katalog terikat canteen/meja.
- **TAHAP:** 7/7 (lifecycle · CRUD+authz · token opaque · rotate · resolve+sesi · security testing · dokumentasi).
- **VERIFIKASI:** 77 test; CI hijau. Demo live: `/q/<valid>` → **302** `/kantin/kantin-pusat` + `Set-Cookie httponly samesite=lax`; `/q/tidak-ada` → **404**.
- **KEAMANAN/DATA:** token CSPRNG ≥128-bit + hash SHA-256 (bukan token mentah); URL tanpa ID numerik (anti-enumeration); rotasi atomik (lock+revoke) → QR lama gagal segera; resolve validasi berlapis; sesi anonim ULID + cookie HttpOnly/SameSite=Lax/Secure-prod/TTL; rate limiter; kegagalan generik 404.
- **REVISI MODUL:** DOC-06-001 (raster QR `chillerlan/php-qrcode` **ditunda**) · 002 (@property cast datetime) · 003 (path modular).

## Pertemuan 07 — Katalog Menu Tenant, Modifier, Stok, dan Public Catalog · LULUS

- **GIT:** PR #12 · merge `7bae344` · tag `pertemuan-07` · Release.
- **OUTPUT:** tenant menu manager Livewire (kategori/menu/toggle habis/stok); **public catalog Livewire** lintas-tenant searchable/filterable. Live: `/kantin/kantin-pusat` menampilkan menu 2 tenant.
- **TAHAP:** 7/7 (aturan sellable · CRUD kategori/menu · modifier/relasi · stok+toggle · public catalog · detail/UX · testing).
- **VERIFIKASI:** 84 test (7 baru via `Livewire::test`); CI hijau; **anti-N+1** (≤6 query/20 menu).
- **KEAMANAN/DATA:** public catalog tanpa bocor lintas-canteen; unavailable/out-of-stock tersembunyi; menu `tenant_id` dari context; **Livewire manager `booted()` re-verify membership + set TenantContext tiap request** (tampering non-anggota → **403**); modifier lintas-tenant ditolak composite FK; stok atomik+auditable; bypass scope terkontrol.
- **REVISI MODUL:** DOC-07-001 (foto WebP `intervention/image` **ditunda**) · 002 (trap Livewire+TenantContext → `booted()` re-verify) · 003 (Livewire SFC).

## Pertemuan 08 — Keranjang Redis per Sesi dan Revalidasi Harga/Stok · LULUS

- **GIT:** PR #15 · merge `0ef56b2` · tag `pertemuan-08` · Release. Docs/output in-body via PR #16.
- **OUTPUT:** `CartService` (Redis) + `ResolveCustomerSession` + DTO `CartView`/`CartLine`; komponen Livewire keranjang + tombol "Tambah" pada katalog (event `cart-add`). Live: scan `/q/<token>` → katalog `kantin-pusat`; tambah 2×Rp16.000 + 1×Rp15.000 → **Subtotal Rp 47.000** (lihat `evidence/pertemuan-08/screenshots/m8-cart.png`).
- **TAHAP:** 7/7 (model penyimpanan Redis per sesi · add/merge + batas · setQuantity/remove/clear · revalidasi harga/stok/ketersediaan · resolver sesi tepercaya · komponen Livewire + wiring katalog · testing & evidence).
- **VERIFIKASI:** 99 test (235 assertions; **+15** — `CartServiceTest` 9, `CartLivewireTest` 4, `ResolveCustomerSessionTest` 2), **Redis + MariaDB nyata**; PHPStan 0; Pint clean; CI hijau. Bukti Redis: key `cart:<ulid>` ada, `TTL≈14400s`, subtotal ikut harga DB (20000→26000 saat harga naik), `clear` menghapus key (`evidence/pertemuan-08/redis-revalidation.txt`).
- **KEAMANAN/DATA:** keranjang **di-key per sesi pelanggan** (ULID dari cookie HttpOnly ter-hash, bukan input klien); Redis hanya menyimpan **identitas + kuantitas** — **harga & stok selalu dihitung ulang dari DB** di `view()` (keranjang tak pernah menentukan harga); menu wajib milik **tenant AKTIF** canteen sesi (injeksi menu lintas-canteen/tenant ditolak di `add()`); modifier lintas-tenant ditolak; hanya `SETEX`/`DEL` **satu key** milik sesi + TTL selaras sesi (**tanpa FLUSHDB**); isolasi antar sesi terbukti (keranjang tak bocor); revalidasi menandai `menu_unavailable`/`insufficient_stock`/`modifier_unavailable`/`price_changed`, subtotal hanya baris `available`, `isOrderable()` menutup checkout bila bermasalah.
- **REVISI MODUL:** DOC-08-001 (keranjang berbasis Redis per sesi menggantikan asumsi session/DB — key ter-scope, TTL selaras sesi, tanpa flush global) · 002 (revalidasi harga/stok dari DB sebagai satu-satunya otoritas; keranjang menyimpan identitas saja) · 003 (event Livewire `cart-add` lintas-komponen; output nyata keranjang in-body).
- **KETERBATASAN:** pelekatan grup modifier ke menu (pivot `menu_modifier_groups`) belum divalidasi di `add()` — validasi kini pada level tenant + ketersediaan; dikencangkan saat UI attach modifier dibangun. Checkout (harga final, pajak/fee, `orders`/`tenant_orders`) menyusul Pertemuan 9.

## Pertemuan 09 — Checkout Atomik, Order Induk, Snapshot, dan Pre-Order · LULUS

- **GIT:** PR #17 · merge `b16743c` · tag `pertemuan-09` · Release. Docs/output in-body via PR #18.
- **OUTPUT:** `CheckoutService` (keranjang→order atomik) + `OrderStatusController` (halaman status publik) + DTO `CheckoutResult` + model `OrderItemModifier`; tombol checkout keranjang + cookie pelacakan. Live: scan `/q/<token>` → tambah item → checkout → **ORD-…** status `awaiting_payment`, split 2 tenant (Ayam Geprek + Kopi Kita), Subtotal Rp 47.000 + Pajak Rp 4.700 + Biaya Rp 940 = **Total Rp 52.640** (`evidence/pertemuan-09/screenshots/m9-order.png`).
- **TAHAP:** 7/7 (revalidasi ulang keranjang · order induk + snapshot · split per-tenant + snapshot komisi · potong stok atomik + idempotensi · pre-order `scheduled_at` · halaman status + pelacakan opaque · testing & evidence).
- **VERIFIKASI:** 109 test (279 assertions; **+10** — `CheckoutServiceTest` 7, `OrderCheckoutFlowTest` 3), **Redis + MariaDB nyata**; PHPStan 0; Pint clean; CI hijau. Bukti (`evidence/pertemuan-09/checkout-proof.txt`): split 2 tenant (komisi 15%/20%), total 78400, stok 10→8 & 10→9, idempoten `order_count=1`+replay.
- **KEAMANAN/DATA:** checkout **atomik** (satu transaksi; gagal → rollback, tanpa order parsial); **revalidasi ulang** harga/stok dari DB (bukan nilai keranjang); **idempoten** via `checkout_key` UNIQUE (klik ganda → order sama; balapan `UniqueConstraintViolation` → order eksisting); **stok atomik berpenjaga** (`WHERE stock_qty >= qty`; dua baris menu sama melebihi stok → rollback penuh, stok utuh, 0 order, 0 movement); **komisi effective-dated** di-snapshot (rate + `commission_id`, composite FK `(tenant_id, commission_id)`); TenantContext tidak aktif saat checkout anonim → `tenant_id` eksplisit per baris; harga/nama/prep dibekukan snapshot append-only; **token pelacakan opaque** (hash SHA-256) via cookie HttpOnly, halaman status **404 generik** tanpa token sah.
- **REVISI MODUL:** DOC-09-001 (checkout menjadi transaksi tunggal atomik + revalidasi ulang; potong stok berpenjaga; idempotensi `checkout_key`) · 002 (komisi/tax/fee di-snapshot per tenant_order; komisi atas subtotal, net = subtotal − komisi; pajak/biaya di luar net tenant) · 003 (pelacakan order via cookie opaque; `order_tracking` dikecualikan dari enkripsi karena token 256-bit hashed; output nyata status pesanan in-body).
- **KETERBATASAN:** stok yang dipotong bersifat reservasi saat checkout; pelepasan kembali saat batal/kedaluwarsa (reversal) menyusul Modul 10–11 (payment/settlement). Stok modifier belum dipotong (hanya stok menu); pembayaran QRIS = Modul 10.

## Pertemuan 10 — Payment Gateway Contract dan QRIS Dinamis Sandbox · LULUS

- **GIT:** PR #19 · merge `e32e71c` · tag `pertemuan-10` · Release. Docs/output in-body via PR #20.
- **OUTPUT:** kontrak `PaymentGateway` + DTO `PaymentChargeRequest`/`QrisCharge` + `FakeQrisGateway` (sandbox) + `PaymentService` (initiate/confirmSandbox) + model `Payment`/`PaymentAttempt`/`PaymentEvent`; komponen Livewire `order-payment` + `ResolveTrackedOrder`. Live: order `awaiting_payment` → "Bayar dengan QRIS" → payload EMVCo dinamis (nominal `540517920`, ref `PAY-…`, `Berlaku hingga …`) + tombol "Simulasi Bayar (sandbox)" (`evidence/pertemuan-10/screenshots/m10-qris.png`).
- **TAHAP:** 7/7 (kontrak gateway bebas provider · FakeQrisGateway + payload EMVCo/CRC16 · model pembayaran · inisiasi idempoten · konfirmasi sandbox + event append-only · UI QRIS Livewire · testing & evidence).
- **VERIFIKASI:** 116 test (309 assertions; **+7** — `PaymentServiceTest` 4, `FakeQrisGatewayTest` 2 unit, `PaymentLivewireTest` 1), **Redis + MariaDB nyata**; PHPStan 0; Pint clean; CI hijau. Bukti (`evidence/pertemuan-10/payment-proof.txt`): provider tunggal `fake-qris-sandbox`, inisiasi idempoten (`payment_count=1`), konfirmasi → payment/order/attempt = paid/success + 1 event `verified`, konfirmasi ulang tetap 1 event.
- **KEAMANAN/DATA:** **satu provider** di-bind (`PaymentGateway`→`FakeQrisGateway`; ganti provider = tukar binding tunggal, tak memasang banyak provider); **idempoten** satu payment per order (UNIQUE `order_id`+`idempotency_key`; balapan → order eksisting); nominal dari `grand_total` (gateway tak menentukan harga); payload **EMVCo + CRC16-CCITT valid**; `confirmSandbox` menirukan callback sukses + **payment_event append-only** (dedup `provider_event_id`), idempoten via status — **bukan** pengganti webhook produksi; simulasi hanya pada gateway sandbox; pelacakan via cookie opaque (hash), status 404 generik tanpa token.
- **REVISI MODUL:** DOC-10-001 (kontrak `PaymentGateway` + satu binding provider; sandbox `FakeQrisGateway` menggantikan gateway nyata) · 002 (payload QRIS dinamis EMVCo + CRC16-CCITT; nominal dari order) · 003 (inisiasi idempoten + `payment_event` append-only sebagai fondasi webhook Modul 11; UI QRIS + simulasi sandbox in-body).
- **KETERBATASAN:** QR **raster** butuh `chillerlan/php-qrcode` (menunggu persetujuan) — payload EMVCo ditampilkan sebagai teks. Kredit saldo tenant (settlement/split/ledger) + **webhook ber-signature idempoten** menyusul Modul 11.

## Pertemuan 11 — Webhook Idempoten, Settlement, Split Allocation, Ledger, dan Reversal · LULUS

- **GIT:** PR #21 · merge `1c1e353` · tag `pertemuan-11` · Release. Docs/output in-body via PR #22.
- **OUTPUT:** `WebhookSignatureVerifier` + `ProcessPaymentWebhook` + `QrisWebhookController` (route `POST /webhooks/qris`) + `SettlePayment` (settle/reverse) + model `LedgerEntry`; `confirmSandbox` kini menjalankan settlement. Live (HTTP nyata): signature valid → `{"status":"ok"}` 200 + settlement; replay → `{"status":"duplicate"}`; signature salah → `{"status":"invalid_signature"}` 401; ledger `sale_credit +40000` & `commission_debit -6000` → saldo `available=34000` (`evidence/pertemuan-11/screenshots/m11-webhook.png`).
- **TAHAP:** 7/7 (verifikasi signature raw body · dedup idempoten event · catat event + lunas + settlement atomik · split allocation per tenant · ledger append-only + saldo · reversal · testing & evidence).
- **VERIFIKASI:** 126 test (345 assertions; **+10** — `WebhookSettlementTest` 5, `SettlePaymentTest` 3, `WebhookSignatureVerifierTest` 2 unit), **Redis + MariaDB nyata**; PHPStan 0; Pint clean; CI hijau. Bukti (`evidence/pertemuan-11/webhook-proof.txt`): ok→duplicate→401, `payment=paid`, ledger split, `balance_available=34000`, 1 event `verified`.
- **KEAMANAN/DATA:** signature **HMAC-SHA256 atas RAW BODY** + `hash_equals`, **fail-closed** (tanpa secret/signature → tolak; invalid → 401 tanpa efek samping); **idempoten** dedup `provider_event_id` UNIQUE (replay → 200 duplicate) + `ledger_entries.idempotency_key` UNIQUE (settlement tak dobel); event+lunas+settlement dalam **satu transaksi** (all-or-nothing → provider kirim ulang bila gagal); **split allocation** per tenant (`sale_credit` +subtotal, `commission_debit` −komisi → net ke `available`; pajak/biaya = bagian platform di luar ledger tenant); **ledger append-only** + **reversal** (negasi + CHECK saldo non-negatif = gagal-tertutup bila dana tak cukup); saldo diupdate atomik (`increment`/`decrement`); CSRF dikecualikan `webhooks/*` (keaslian via signature).
- **REVISI MODUL:** DOC-11-001 (webhook ber-signature HMAC atas raw body + fail-closed; CSRF dikecualikan) · 002 (idempotensi berlapis: dedup event + ledger key; pemrosesan atomik) · 003 (settlement split ke ledger append-only + saldo materialisasi; reversal menggantikan edit historis; output nyata alur webhook in-body).
- **KETERBATASAN:** settlement kredit langsung ke `available` (siklus `hold`/`release` terikat penyelesaian order = penyempurnaan lanjutan). Reversal gagal-tertutup bila dana telah ditarik (kebijakan clawback di luar lingkup). Penarikan (withdrawal) = Modul 13.

## Pertemuan 12 — Kitchen Display System Realtime, Status, dan Notifikasi · LULUS

- **GIT:** PR #23 · merge `dbd632f` · tag `pertemuan-12` · Release. Docs/output in-body via PR #24.
- **OUTPUT:** `KitchenService` (mesin status) + event broadcast `TenantOrderStatusChanged`/`NewTenantOrderReceived` + `TenantChannels` (otorisasi channel) + komponen Livewire `kitchen-board` + rute tenant `/kitchen`; `SettlePayment` menyiarkan order baru saat lunas. Live: papan KDS "Ayam Geprek Mantul" — kolom Baru/Diterima/Disiapkan/Siap; satu pesanan maju `Baru → Diterima` (`evidence/pertemuan-12/screenshots/m12-kitchen.png`).
- **TAHAP:** 7/7 (mesin status + validasi transisi · event broadcast + channel privat · otorisasi keanggotaan channel · komponen KDS Livewire (booted re-verify) · siaran order baru saat settlement · listener Echo + wire:poll fallback · testing & evidence).
- **VERIFIKASI:** 133 test (363 assertions; **+7** — `KitchenServiceTest` 3, `TenantChannelsTest` 1, `KitchenBoardLivewireTest` 3), **Redis + MariaDB nyata**; PHPStan 0; Pint clean; CI hijau.
- **KEAMANAN/DATA:** **state machine** `pending→accepted→preparing→ready→completed` (pending/accepted→cancelled); transisi tak sah **ditolak** (`KitchenException`) · **channel privat** `tenant.{id}.orders` hanya untuk **anggota** (`UserTenantRole`) — cegah kebocoran antrean lintas tenant · KDS Livewire `booted()` **re-verify membership** (non-anggota → **403**) + set `TenantContext` (query ter-scope) · `NewTenantOrderReceived` **ShouldDispatchAfterCommit** (tak ada siaran hantu saat rollback) · perubahan status ter-audit; hanya pesanan berstatus `paid` yang tampil di dapur.
- **REVISI MODUL:** DOC-12-001 (mesin status tenant_order tervalidasi + audit; transisi tak sah ditolak) · 002 (broadcasting Reverb ke channel privat per tenant + otorisasi keanggotaan; siaran setelah commit) · 003 (KDS Livewire dengan re-verify membership + TenantContext; output nyata papan dapur in-body).
- **KETERBATASAN:** koneksi Reverb live memerlukan server Reverb berjalan (realtime diverifikasi lewat dispatch event + otorisasi kanal, bukan koneksi WebSocket end-to-end di CI). Notifikasi pelanggan (status siap) dapat memakai channel/notification lanjutan. Rilis `release` held→available terikat penyelesaian pesanan = penyempurnaan lanjutan.

---

## Integrasi Laravel Boost (PR #9)

- `laravel/boost ^2.5` (require-dev) + `CLAUDE.md` (guidelines) + `.claude/skills/*` (Livewire, Flux UI, Fortify, Echo, Tailwind, Laravel best practices, infer-conventions) + `.mcp.json` + `boost.json`.
- Diadopsi sejak Pertemuan 6: `php artisan make:*` untuk scaffold, PHPUnit (`make:test --phpunit`, `--filter`/`--compact`), `pint --dirty --format agent`, konvensi PHP 8 (curly braces, constructor promotion, return types, PHPDoc array shape), route bernama, aktivasi skill domain, test enforcement.
- Catatan: task ini secara eksplisit meminta evidence/ADR/docs, sehingga tetap dibuat meski Boost default konservatif soal dokumentasi. Boost MCP tools (search-docs, database-*) tidak ter-registrasi pada sesi ini; guidelines diikuti via `CLAUDE.md` + skill.
