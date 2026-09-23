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
| 7 | Katalog Menu, Modifier, Stok, Public Catalog | LULUS | `pertemuan-07`* | (PR ini) | #35 | 93 |
| 8 | Keranjang Redis & Revalidasi Harga/Stok | BELUM | — | — | — | — |
| 9–14 | (Checkout … Rilis) | BELUM | — | — | — | — |

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

- **GIT:** PR #35 → `rebuild/modular-v2` · tag `pertemuan-07` dipindah di akhir rebuild (v1: PR #12).
- **OUTPUT:** komponen Livewire **`catalog::menu-manager`** dan **`catalog::menu-catalog`** (SFC di `app/Modules/Catalog/resources/views/livewire`), halaman `catalog::tenant.menu-manager`, route `tenant.menu-manager` di modul Catalog; `MenuStockService`.
- **TAHAP:** 7/7.
- **VERIFIKASI:** 93 test (331 assertions) termasuk halaman menu-manager merender komponen modul; anti-N+1 (≤6 query); CI hijau.
- **KEAMANAN/DATA:** katalog publik tanpa bocor lintas-canteen; `tenant_id` dari context; `booted()` re-verify membership (tampering → 403); stok atomik + audit.
- **REVISI MODUL:** DOC-07-001..003 · **004** (komponen/halaman/route milik modul Catalog) · **005** (BUAT FILE; `make:livewire catalog::…` langsung menulis ke folder modul).

---

## Integrasi Laravel Boost (PR #9)

- `laravel/boost ^2.5` (require-dev) + `CLAUDE.md` (guidelines) + `.claude/skills/*` (Livewire, Flux UI, Fortify, Echo, Tailwind, Laravel best practices, infer-conventions) + `.mcp.json` + `boost.json`.
- Diadopsi sejak Pertemuan 6: `php artisan make:*` untuk scaffold, PHPUnit (`make:test --phpunit`, `--filter`/`--compact`), `pint --dirty --format agent`, konvensi PHP 8 (curly braces, constructor promotion, return types, PHPDoc array shape), route bernama, aktivasi skill domain, test enforcement.
- Catatan: task ini secara eksplisit meminta evidence/ADR/docs, sehingga tetap dibuat meski Boost default konservatif soal dokumentasi. Boost MCP tools (search-docs, database-*) tidak ter-registrasi pada sesi ini; guidelines diikuti via `CLAUDE.md` + skill.

---

## Revisi v2 — Modul sebagai Vertical Slice (2026-09-23) · DIKERJAKAN BERTAHAP

- **STATUS:** Pertemuan 2–7 dibangun ulang dan LULUS di `rebuild/modular-v2`; Pertemuan 8–14 menyusul dengan pola yang sama. `main` dan tag lama belum diubah.
- **GIT:** tag arsip `arsip-v1/pertemuan-01…14` (rantai lama utuh); branch integrasi `rebuild/modular-v2` dari akhir Pertemuan 1 (`87d0de7`); PR #29–#35 per pertemuan (rebase merge, CI hijau). Satu kali force-push (with-lease, atas persetujuan pemilik) ke `rebuild/modular-v2` untuk menyisipkan blok BUAT FILE Modul 2–4 ke rentang pertemuannya.
- **OUTPUT:** setiap modul memiliki `Http/Controllers`, `Http/Requests`, `routes/{portal}.php`, `resources/views` (+ `livewire/`) sendiri; model, policy, middleware, layout, dan komponen UI tetap shared kernel; `modul_praktikum/` per pertemuan.
- **TAHAP:** per pertemuan: putar ulang commit v1 → pindahkan berkas fitur ke modul pemilik → quality gate → revisi DOCX in-body (path, Output Nyata b, BUAT FILE) → pecah ulang `modul_praktikum/` → PR → CI → merge.
- **PERUBAHAN:** `ModuleServiceProvider`, `PortalRoutes` (`customer/tenant/admin/web`), limiter milik modul, scope `CommissionScheme::effectiveAt()`, `ModuleConventionTest`, `CommissionScheduleBoundaryTest`.
- **VERIFIKASI:** tiap pertemuan Pint + PHPStan 0 + suite penuh di MariaDB nyata + CI; perintah BUAT FILE dijalankan di worktree bersih dan berkas hasilnya dicocokkan dengan berkas baru pertemuan (selisih hanya screenshot evidence / `mariadb-dump`).
- **KEAMANAN/DATA:** tak ada `.env`/secret di Git; worktree verifikasi memakai salinan `.env` di scratchpad (di luar repo); tidak ada FLUSHDB/penghapusan global; tidak ada dependency baru.
- **REVISI MODUL:** DOC-02-002/003, DOC-03-004, DOC-04-003/004, DOC-05-004/005/006, DOC-06-004/005, DOC-07-004/005.
- **KETERBATASAN:** render DOCX penuh per halaman `TIDAK DIUJI` (tanpa LibreOffice/Word-automation; struktur XML, idempotensi patch, dan pratinjau Quick Look/`textutil` diverifikasi).
- **BERIKUTNYA:** Pertemuan 8 (keranjang Redis → komponen `ordering::cart`), lalu 9–14; checkout M9 memakai `CommissionScheme::effectiveAt()`.

