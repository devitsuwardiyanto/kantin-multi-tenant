# Requirements Traceability Matrix (RTM)

Sumber requirement: `docs/SRS_Aplikasi_Kantin_Multi_Tenant_ISO29148 v2.pdf` (**SRS v2**, 23 use case) dan
mockup `docs/Mockup UI Kantin Multi-Tenant-v2.dc.html` (layar `#uc-NN`). Rantai v3: mulai Pertemuan 5 setiap
pertemuan menuntaskan minimal satu use case; matriks ini diperbarui per pertemuan dan dilengkapi pada Pertemuan 14.

| Use case | Kebutuhan | Pertemuan | Implementasi utama | Test penerimaan | Status |
|---|---|---|---|---|---|
| UC-19 Autentikasi Pengguna | FR-TEN-01 | 5 | `app/Providers/FortifyServiceProvider.php` (authenticateUsing), `app/Support/Auth/LoginLockout.php`, `app/Http/Controllers/DashboardRedirectController.php`, `config/session.php` | `tests/Feature/Auth/LoginLockoutTest.php` + uji browser | PASS |
| UC-21 Kelola Tenant & Skema Komisi | FR-ADM-01 | 5 | `app/Modules/Admin/Services/{CreateTenant,ChangeTenantStatus,ChangeCommissionSchedule}.php`, `app/Modules/Admin/Http/Controllers/*`, view `admin::tenants.*` | `tests/Feature/TenantOnboardingTest.php`, `AdminManagementTest`, `CommissionScheduleBoundaryTest` + uji browser | PASS |

Rencana: P6 UC-22, UC-02 · P7 UC-13, UC-14, UC-01, UC-11 · P8 UC-03, UC-04 · P9 UC-05, UC-06 · P10 UC-07 ·
P11 UC-08, UC-10 · P12 UC-15, UC-09, UC-12 · P13 UC-16, UC-17, UC-18, UC-20, UC-23 · P14 uji penerimaan 23 UC.
