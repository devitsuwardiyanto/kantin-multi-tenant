# Pertemuan 03 — Stage Checklist

Output: migrasi seluruh tabel baseline (30) dari ERD, model/relasi inti, factory/seeder 2 tenant, schema dump + data dictionary.

| Tahap | Aktivitas | Status | Bukti |
|---|---|---|---|
| 1 | Audit ERD & urutan dependensi | ✅ | 30 tabel dipetakan 6 domain; parent→child; `docs/data-dictionary.md` |
| 2 | Migrasi tenancy & identitas | ✅ | canteens/tenants/balances/bank/roles; UQ (canteen_id,code) menolak duplikat |
| 3 | Migrasi meja/katalog/komisi | ✅ | token varbinary(32); composite FK modifier lintas-tenant **gagal 1452** |
| 4 | Migrasi order/payment/ledger/outbox | ✅ | orders tanpa tenant_id; 1 order → 2 tenant_orders; append-only + idempotency UQ |
| 5 | Model, factory, seeder | ✅ | 15 model + factory; seeder 1 canteen/2 tenant **idempoten** (run ke-2 tak menambah baris) |
| 6 | Verifikasi schema & query | ✅ | migrate:fresh + rollback aman; uji negatif constraint hijau; schema-dump.sql (39 CREATE TABLE) |
| 7 | Dokumentasi & commit | ✅ | data-dictionary, ADR-0007, DOC-03-001..003, PR + tag pertemuan-03 |
