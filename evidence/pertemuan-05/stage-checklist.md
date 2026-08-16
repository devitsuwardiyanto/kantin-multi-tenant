# Pertemuan 05 — Stage Checklist

Output: halaman admin tenant/operator, service komisi atomik, workflow rekening, feature test (policy/validation/overlap/audit).

| Tahap | Aktivitas | Status | Bukti |
|---|---|---|---|
| 1 | Rancang workflow admin | ✅ | ADR-0009 (state, ability owner/manager/finance, field audit) |
| 2 | CRUD tenant + Form Request | ✅ | StoreTenantRequest (unique per canteen); CreateTenant transaksi → tenant+komisi+balance |
| 3 | Penugasan user & role | ✅ | AssignTenantRole (assign/remove); owner terakhir dilindungi |
| 4 | Komisi effective-dated | ✅ | ChangeCommissionSchedule (lock+tutup+insert); guard DB active_lock → 1062 |
| 5 | Rekening & audit | ✅ | encrypted cast + last4; one-primary guard; AuditLogger append-only (sanitasi) |
| 6 | Uji UI & aturan bisnis | ✅ | 9 admin test (policy, unique, overlap, primary, last-owner, audit, encrypted) |
| 7 | Dokumentasi & commit | ✅ | ADR-0009, DOC-05-001..003, PR + tag pertemuan-05 |
