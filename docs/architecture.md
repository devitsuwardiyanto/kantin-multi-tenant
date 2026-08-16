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

Struktur per modul: `app/Modules/{Modul}/{Actions,Data,Services}` + `{Modul}ServiceProvider`.
Provider terdaftar di `bootstrap/providers.php` (satu provider per bounded context).

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
| Pelanggan (publik) | `routes/customer.php` | `kantin/{canteen}` | `customer.*` | `web` |
| Operator tenant | `routes/tenant.php` | `tenant/{tenant}` | `tenant.*` | `web, auth, verified, role:tenant` |
| Pengelola kantin | `routes/admin.php` | `admin` | `admin.*` | `web, auth, verified, role:admin` |

Registrasi di `bootstrap/app.php` (`withRouting(then: ...)`). **Modul 4**: grup tenant memakai
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
- Nama route: `{konteks}.{aksi}` — unik, dipakai controller/redirect/test/Blade.
- Blade `{{ }}` selalu ter-escape; `{!! !!}` hanya untuk HTML tersanitasi.
