# Pertemuan 02 — Security Verification

Enforcement server-side untuk isolasi konteks (bukan penyembunyian menu).

| Skenario | Ekspektasi | Hasil (test) |
|---|---|---|
| Guest → `tenant.dashboard` / `admin.dashboard` | redirect ke login | ✅ redirect |
| `customer.home` (publik) | 200 tanpa auth | ✅ 200 |
| role `tenant` → `tenant.dashboard` | 200 | ✅ |
| role `admin` → `admin.dashboard` | 200 | ✅ |
| role `tenant` → `admin.dashboard` | **403** | ✅ forbidden |
| role `admin` → `tenant.dashboard` | **403** | ✅ forbidden |
| role `null` → konteks internal | **403** | ✅ forbidden |
| status `suspended` (tenant) → `tenant.dashboard` | **403** | ✅ forbidden |

- Mass assignment: `role`/`status` **tidak** fillable; hanya di-set via kode tepercaya (seeder/test).
- Cross-tenant matrix penuh dimulai Modul 4 (belum ada entitas tenant).
