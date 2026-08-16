# Pertemuan 05 — Security/Data Verification

| Kontrol | Hasil |
|---|---|
| Policy admin (viewAny/create/update/scheduleCommission/manageBank/assignRole) | ✅ non-manager 403 |
| Canteen dari konteks tepercaya (bukan input) | ✅ diturunkan dari user_canteen_roles |
| Form Request authorize + rules; hanya validated() ke service | ✅ |
| Komisi tidak overlap (service) + guard DB (1062) | ✅ |
| Order lama tidak berubah saat tarif baru | ✅ effective-dated + snapshot (Modul 3/9) |
| Nomor rekening terenkripsi (bukan plaintext di DB) + last4 | ✅ ciphertext di DB; decrypt via cast |
| Audit tidak memuat nomor rekening mentah | ✅ AuditLogger.sanitize() buang field sensitif |
| Satu primary account/tenant | ✅ guard primary_lock (1062) + service unset lain |
| Owner terakhir dilindungi | ✅ DomainException |
| Audit append-only | ✅ hanya create; koreksi = event baru |
