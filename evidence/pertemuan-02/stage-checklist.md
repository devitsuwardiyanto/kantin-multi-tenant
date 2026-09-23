# Pertemuan 02 — Stage Checklist

Output: struktur `app/Modules/*` + provider, route customer/tenant/admin bernama & ter-guard, 3 layout, smoke test auth/authorization.

| Tahap | Aktivitas | Status | Bukti |
|---|---|---|---|
| 1 | Petakan bounded context | ✅ | `docs/architecture.md` (6 modul + aturan dependency), ADR-0006 |
| 2 | Struktur & provider modul | ✅ | `app/Modules/{6}` vertical slice (`Http/Controllers`, `routes`, `resources/views/livewire`) + provider turunan `ModuleServiceProvider`; `php artisan about` OK; `ModuleConventionTest` (route/view/Livewire modul tiruan) hijau |
| 3 | Route per konteks | ✅ | `PortalRoutes` + `routes/{customer,tenant,admin}.php`; `route:list` tanpa nama duplikat (context-routes.txt) |
| 4 | Tiga layout | ✅ | `x-layouts.{customer,tenant,admin}` + `x-button/input/status-badge/empty-state`; target sentuh min-h-11 (44px); customer render (screenshot) |
| 5 | Auth & navigasi | ✅ | `users.role/status`, `User::hasRole()`, middleware `role` → 403; test wrong-role/suspended/no-role |
| 6 | Testing fondasi | ✅ | 49 test hijau (16 baru), Pint, PHPStan 0 |
| 7 | Dokumentasi & commit | ✅ | `docs/architecture.md`, ADR-0006, PR + tag pertemuan-02 |

Catatan sekuens: model binding `scopeBindings` ditunda ke Modul 4 (DOC-02-001).
