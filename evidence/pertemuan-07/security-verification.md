# Pertemuan 07 — Security/Data Verification

| Kontrol | Hasil |
|---|---|
| Public catalog tidak bocor lintas-canteen | ✅ hanya menu canteen dari sesi QR (test) |
| Unavailable/out-of-stock tersembunyi | ✅ scopeSellable (is_available + stock>0) |
| Menu tenant_id dari context (bukan input) | ✅ auto-fill; payload tenant_id palsu diabaikan |
| Livewire tenant manager: context re-establish + re-verify tiap request | ✅ booted(): membership check → 403 bila tampering (prop publik tak dipercaya) |
| Modifier lintas-tenant ditolak | ✅ composite FK (Modul 3) + tenant sama pada pivot |
| Stok atomik + auditable | ✅ transaksi+lock; movement idempoten (unique); audit |
| Bypass scope terkontrol (public) | ✅ withoutGlobalScope + filter canteen/status/sellable eksplisit |
| Anti N+1 | ✅ eager load; ≤6 query untuk 20 menu |
