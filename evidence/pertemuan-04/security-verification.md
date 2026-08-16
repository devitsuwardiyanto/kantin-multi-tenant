# Pertemuan 04 — Security Verification (matriks allow/deny)

Isolasi berlapis tenant A vs B, diuji di MariaDB nyata.

| # | Skenario | Ekspektasi | Hasil |
|---|---|---|---|
| 1 | Global scope: query context=A | hanya baris tenant A | ✅ (2 vs 5) |
| 2 | Tanpa context | scope tidak memfilter (mitigasi: route selalu isi context) | ✅ |
| 3 | Context scoped reset antar request/job | tidak bocor | ✅ forgetScopedInstances → has()=false |
| 4 | Auto-fill tenant_id saat create dalam context | tenant_id = A | ✅ |
| 5 | `GET tenant/{A}/menus` sebagai anggota | hanya menu A | ✅ (2 item) |
| 6 | `GET tenant/{A}/menus/{menuB}` | scoped binding → 404 | ✅ NotFound |
| 7 | `PATCH tenant/{A}/menus/{menuA}` anggota | 200 toggle | ✅ |
| 8 | `MenuPolicy::update` menu tenant lain | false | ✅ ditolak |
| 9 | Public catalog canteen1 | hanya tenant aktif canteen1 (bukan canteen2/suspended) | ✅ (2 item) |
| 10 | Job per tenant (A lalu B) | context init+clear, tak terkontaminasi | ✅ (2, lalu 3) |
| 11 | Tenant suspended / non-anggota → dashboard | 403 | ✅ |
| 12 | Guest → route tenant/admin | redirect login | ✅ |

Composite FK DB (Modul 3) tetap menjadi benteng penyimpanan (cross-tenant insert → 1452).
