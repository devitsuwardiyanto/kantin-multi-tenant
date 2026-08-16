# Pertemuan 03 — Known Issues

| # | Severity | Isu | Status |
|---|---|---|---|
| 1 | Info | SQL baseline `00_database` tidak disertakan; skema diturunkan dari ERD | DOC-03-001; audit bila SQL tersedia |
| 2 | Info | `binary(32)` → `varbinary(32)` (Laravel) | DOC-03-002; setara fungsional |
| 3 | Info | Model dibuat inti-minimal (15/30 tabel); sisanya menyusul per modul | Sesuai scope Modul 3 |
| 4 | Low | `Model::preventLazyLoading()` belum diaktifkan | Ditunda (hindari kejut starter kit); hardening Modul 14 |
| 5 | Info | Global scope/policy tenant belum ada | Diperkenalkan Modul 4 |
