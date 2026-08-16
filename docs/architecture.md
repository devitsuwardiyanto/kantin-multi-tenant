# Arsitektur — Kantin Multi-Tenant (Modular Monolith)

Satu aplikasi Laravel, satu database, satu deployment. Batas domain dibuat eksplisit di kode
(`app/Modules/*`), bukan lewat pemisahan proses. Diperkenalkan pada Pertemuan 2 (FR-TEN-01 / UC-19).

## Modul & tanggung jawab

| Modul | Tanggung jawab | Diisi |
|---|---|---|
| `Admin` | Administrasi kantin, tenant, role, komisi, rekening. Pemilik route `admin.*`. | Modul 5 |
| `Catalog` | Kategori, menu, modifier, stok tenant, public catalog. | Modul 7 |
| `Ordering` | Keranjang (Redis), checkout atomik, order induk, snapshot. Pemilik route `customer.*`. | Modul 8–9 |
| `Payments` | Kontrak `PaymentGateway`, adapter, webhook, settlement, split ledger, outbox. | Modul 10–11 |
| `Kitchen` | Kitchen Display System realtime, state machine order, notifikasi. | Modul 12 |
| `Reporting` | Laporan scoped, rekonsiliasi, ekspor async, withdrawal. | Modul 13 |

### Struktur per modul (vertical slice)

Setiap modul **memiliki semua file fiturnya** di satu folder, sehingga satu fitur dikerjakan di satu tempat:

```
app/Modules/{Modul}/
├── {Modul}ServiceProvider.php   ← extends App\Modules\ModuleServiceProvider (alias: huruf kecil)
├── Http/Controllers/            ← controller milik fitur modul
├── Actions/  Data/  Services/   ← logika aplikasi/domain (tanpa HTTP)
├── routes/{customer,tenant,admin}.php   ← route fitur, dibungkus PortalRoutes::{portal}()
└── resources/views/             ← view('{alias}::...')
    └── livewire/                ← <livewire:{alias}::nama-komponen />
```

`ModuleServiceProvider::boot()` memuat otomatis `routes/*.php`, namespace view `{alias}::`, dan
namespace Livewire `{alias}::`. Provider terdaftar di `bootstrap/providers.php` (satu per bounded context).

**Shared kernel (tetap di lokasi standar Laravel, dipakai lintas modul):** model Eloquent `app/Models`
(satu database dipakai banyak modul), middleware `app/Http/Middleware`, layout & komponen UI
`resources/views/components`, dashboard portal, dan `app/Support` (routing portal, tenancy, token).

## Aturan dependency (arah menuju kontrak/domain)

```
UI (routes, Livewire, Blade)  ->  Application (Actions/Services)  ->  Domain/Contracts
                                                                   ^
Infrastructure (Eloquent, HTTP, vendor)  ------------------------- |
```

- Modul lain **tidak** mengakses controller/tabel internal modul lain secara langsung.
- Komunikasi lintas modul memakai **event** atau **interface/service** yang disepakati
  (mis. `Payments` mengekspos `PaymentGateway`; `Ordering` memancarkan event, `Kitchen` menyimak).
- Framework tidak menegakkan batas ini; disiplin dijaga lewat review, provider, dan test.

## Routing per konteks

| Konteks | File | Prefix | Name | Middleware |
|---|---|---|---|---|
| Pelanggan (publik) | `routes/customer.php` + `app/Modules/*/routes/customer.php` | `kantin/{canteen}` | `customer.*` | `web` |
| Operator tenant | `routes/tenant.php` + `app/Modules/*/routes/tenant.php` | `tenant/{tenant:slug}` | `tenant.*` | `web, auth, verified, tenant` (SetTenantContext) + `scopeBindings` |
| Pengelola kantin | `routes/admin.php` + `app/Modules/*/routes/admin.php` | `admin` | `admin.*` | `web, auth, verified, role:admin` |

Grup tiap portal (prefix + name + middleware) didefinisikan **tunggal** di `App\Support\Routing\PortalRoutes`.
`bootstrap/app.php` (`withRouting(then: ...)`) memakainya untuk route inti portal (dashboard), dan file route
modul memakainya untuk route fitur — route modul tidak mungkin lupa memasang lapis keamanan portal.
Test `ModuleConventionTest` menjaga invarian: setiap route `tenant.*`/`admin.*` wajib membawa middleware portalnya. **Modul 4**: `PortalRoutes::tenant()` memakai
`tenant/{tenant:slug}` + `scopeBindings()` + resolver `SetTenantContext` (alias `tenant`) — mengikat
model Tenant, memverifikasi membership + status, mengisi TenantContext. `{canteen:slug}` menyusul Modul 6.

## Isolasi tenant berlapis (Modul 4)
Empat lapis (lihat [ADR-0008](adr/0008-tenant-isolation.md)): (1) `TenantContext` scoped per request/job;
(2) global scope + auto-fill via trait `BelongsToTenant`; (3) resolver middleware + membership check;
(4) policy + scoped route binding. Ditambah composite FK/unique DB (Modul 3). Bypass publik lintas-tenant
hanya via `PublicCatalogQuery` (`withoutGlobalScope` eksplisit + filter canteen/status). Cross-tenant → 404/403.

## Authorization (server-side)

- `auth` menolak tamu; **tidak** cukup untuk isolasi antar-konteks/tenant.
- Middleware `role:{admin|tenant}` (`EnsureUserHasRole`) menolak role/status yang tidak cocok dengan **403**.
- Menu yang disembunyikan hanya UX; setiap route internal tetap diuji terhadap akses langsung.
- Kontrak role interim: kolom `users.role` + `users.status` + `User::hasRole()`. Policy & relasi role
  penuh (tenant membership, effective-dated) diformalkan **Modul 4–5**.

## Layout & komponen UI

- `x-layouts.customer` (mobile-first, `max-w-md`), `x-layouts.tenant` (sidebar dapat diciutkan, Alpine),
  `x-layouts.admin` (tabel responsif `overflow-x-auto`).
- Komponen: `x-button`, `x-input`, `x-status-badge`, `x-empty-state`. Target sentuh ≥ 44px (`min-h-11`).
- Tailwind v4 + Flux appearance (dark mode via `@fluxAppearance`).

## Konvensi
- Namespace: `App\Modules\{Modul}\...` (PSR-4 via `App\` → `app/`).
- Alias view/Livewire modul = nama modul huruf kecil (`catalog::`, `ordering::`, ...).
- Blade/SFC di `app/Modules/*/resources` dikecualikan dari PHPStan (bukan kelas PHP murni; dicakup test).
- Nama route: `{konteks}.{aksi}` — unik, dipakai controller/redirect/test/Blade.
- Blade `{{ }}` selalu ter-escape; `{!! !!}` hanya untuk HTML tersanitasi.
