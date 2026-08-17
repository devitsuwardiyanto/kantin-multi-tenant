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
| 9–14 | (Checkout … Rilis) | BELUM | — | — | — | — |

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

---

## Integrasi Laravel Boost (PR #9)

- `laravel/boost ^2.5` (require-dev) + `CLAUDE.md` (guidelines) + `.claude/skills/*` (Livewire, Flux UI, Fortify, Echo, Tailwind, Laravel best practices, infer-conventions) + `.mcp.json` + `boost.json`.
- Diadopsi sejak Pertemuan 6: `php artisan make:*` untuk scaffold, PHPUnit (`make:test --phpunit`, `--filter`/`--compact`), `pint --dirty --format agent`, konvensi PHP 8 (curly braces, constructor promotion, return types, PHPDoc array shape), route bernama, aktivasi skill domain, test enforcement.
- Catatan: task ini secara eksplisit meminta evidence/ADR/docs, sehingga tetap dibuat meski Boost default konservatif soal dokumentasi. Boost MCP tools (search-docs, database-*) tidak ter-registrasi pada sesi ini; guidelines diikuti via `CLAUDE.md` + skill.
