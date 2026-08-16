# Pertemuan 04 — Stage Checklist

Output: TenantContext scoped + resolver, trait BelongsToTenant, policy + scoped binding, matriks allow/deny A vs B.

| Tahap | Aktivitas | Status | Bukti |
|---|---|---|---|
| 1 | Definisikan konteks & aturan akses | ✅ | ADR-0008 (4 lapis + bypass terkontrol) |
| 2 | Implementasi TenantContext | ✅ | `TenantContext` scoped; test reset antar request/job (forgetScopedInstances) |
| 3 | Middleware resolver + role check | ✅ | `SetTenantContext`: {tenant:slug}+membership+status; suspended/non-anggota → 403, tak ada → 404 |
| 4 | Global scope + model integration | ✅ | trait `BelongsToTenant` (scope+auto-fill) pada 8 model; query hanya tenant aktif; tenant_id non-fillable |
| 5 | Policy + scoped route binding | ✅ | `MenuPolicy`/`TenantOrderPolicy`/`WithdrawalPolicy`; scopeBindings; menu tenant lain → 404; update silang ditolak |
| 6 | Public query + bypass terkontrol | ✅ | `PublicCatalogQuery` withoutGlobalScope + filter canteen/status; no cross-canteen/suspended leak |
| 7 | Security tests + commit | ✅ | 10 uji isolasi hijau; PR + tag pertemuan-04 |
