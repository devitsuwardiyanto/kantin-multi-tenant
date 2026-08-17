# Pertemuan 07 — Stage Checklist

Output: tenant menu manager + toggle habis cepat, modifier min/max, public catalog searchable/filterable, feature/Livewire test.

| Tahap | Aktivitas | Status | Bukti |
|---|---|---|---|
| 1 | Aturan katalog | ✅ | `Menu::scopeSellable` (is_available + stock>0); ADR-0011 |
| 2 | CRUD kategori & menu | ✅ | Livewire menu-manager; create menu → tenant_id **auto dari context** (test) |
| 3 | Modifier & relasi menu | ✅ | `Menu::modifierGroups` pivot (tenant_id); komposit FK lintas-tenant ditolak (Modul 3) |
| 4 | Stok & toggle | ✅ | MenuStockService atomik (lock+movement idempoten+audit); toggle is_available |
| 5 | Public catalog Livewire | ✅ | PublicCatalogQuery::browse (bypass terkontrol, eager load anti-N+1 ≤6 query, paginate, URL-bound) |
| 6 | Detail menu & UX | ✅ | katalog mobile-first (screenshot); harga Rupiah; empty/loading state |
| 7 | Testing & commit | ✅ | 7 test (catalog scope/search/N+1/render; manager create/toggle/restock/403) |
