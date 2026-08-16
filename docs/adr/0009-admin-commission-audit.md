# ADR-0009 — Administrasi, komisi effective-dated, dan audit

- Status: Diterima
- Tanggal: 2026-08-17
- Konteks: Back-office mengubah data yang memengaruhi akses & uang. FR-ADM-01/UC-21; FR-TEN-01/UC-19.

## Keputusan
1. **Validasi + otorisasi berlapis**: Form Request (`authorize()` + `rules()`); controller hanya
   meneruskan `validated()`. Keputusan dipusatkan di **TenantPolicy** (viewAny/create/update/
   scheduleCommission/manageBank/assignRole) berbasis `user_canteen_roles`, bukan string role tersebar.
2. **Canteen dari konteks tepercaya**: canteen aktor diturunkan dari keanggotaannya, bukan input request.
3. **Komisi effective-dated** (`ChangeCommissionSchedule`): transaksi + `lockForUpdate()` versi aktif,
   tutup rentang lama (`valid_to`), insert versi baru; overlap ditolak. **Guard DB (lapis terakhir)**:
   generated column `active_lock = IF(valid_to IS NULL, tenant_id, NULL)` + UNIQUE → satu skema aktif/tenant.
   Snapshot komisi pada order (Modul 3/9) memutus ketergantungan histori terhadap tarif aktif.
4. **Rekening**: `encrypted` cast pada `account_number_cipher`, `account_last4` untuk tampilan; nomor mentah
   tak pernah di DB plaintext/log/audit. Satu primary/tenant via generated column `primary_lock` + UNIQUE.
5. **Audit append-only** (`AuditLogger`): actor/tenant/canteen/action/target/request_id + diff disanitasi
   (field sensitif dibuang); koreksi = event baru.
6. **Owner terakhir** dilindungi (`AssignTenantRole::remove`).

## Konsekuensi
- Onboarding tenant atomik (tenant + komisi aktif + balance) via `CreateTenant`.
- `ChangeCommissionSchedule::handle` menerima `CarbonInterface` (aplikasi memakai CarbonImmutable).
- Diuji: policy deny, unique per canteen, overlap+guard DB (1062), encrypted+last4, one-primary, last-owner, audit.
