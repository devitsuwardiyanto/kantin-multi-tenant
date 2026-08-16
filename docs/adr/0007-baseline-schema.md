# ADR-0007 — Skema baseline multi-tenant

- Status: Diterima
- Tanggal: 2026-08-16
- Konteks: ERD Fase 0 (30 tabel) diterjemahkan ke migrasi Laravel. SQL baseline `00_database`
  yang disebut modul tidak tersedia di `references/` (DOC-03-001); skema diturunkan dari ERD + invariant.

## Keputusan
1. **Uang** disimpan `unsignedBigInteger` (Rupiah integer). **Rate** `decimal(6,4)` (exact). Tidak ada FLOAT.
2. **Isolasi lintas-tenant di DB**: setiap parent tenant-owned punya UNIQUE `(tenant_id, id)`; child
   memakai **composite FK** `(tenant_id, x_id)`. Global scope (Modul 4) melengkapi, DB adalah benteng akhir.
3. **orders** platform-scoped tanpa `tenant_id`; **tenant_orders** menyimpan tenant_id + snapshot komisi/harga.
4. **Token** disimpan sebagai hash `binary(32)` (Laravel `binary(32)` → `varbinary(32)`, setara; DOC-03-002).
5. **Append-only**: payment_events, ledger_entries, audit_logs (koreksi via reversal).
6. **Idempotency**: UNIQUE key pada operasi kritis (checkout, stock movement, ledger, withdrawal, webhook event).
7. **customer_sessions** memakai PK ULID (sortable, tidak menebak sekuens).
8. Model dibuat inti-minimal untuk alur pemesanan; sisanya menyusul per modul.

## Konsekuensi
- Constraint diuji pada MariaDB nyata (bukan SQLite) — uji negatif lintas-tenant gagal 1452.
- `migrate:fresh`/rollback simetris; schema dump tersedia sebagai evidence.
- `preventLazyLoading()` belum diaktifkan (menghindari kejut pada starter kit); ditunda sebagai hardening.
