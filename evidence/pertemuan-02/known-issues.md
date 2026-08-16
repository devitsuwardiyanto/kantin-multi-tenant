# Pertemuan 02 — Known Issues

| # | Severity | Isu | Dampak | Status |
|---|---|---|---|---|
| 1 | Info | Model binding `{tenant:slug}`/`scopeBindings` belum aktif | Parameter route masih string placeholder | Direncanakan Modul 4 (DOC-02-001) |
| 2 | Info | Route context memakai closure placeholder | Tidak route:cache-friendly bila di-cache | Diganti Livewire/controller pada Modul 5/7/9 |
| 3 | Info | Redirect pascalogin belum context-aware | Semua login → `/dashboard` default | Ditambah saat konteks tenant punya identitas (Modul 4–5); dijaga agar test lama tetap hijau |
| 4 | Low | Role interim berupa kolom string | Belum ada relasi role/tenant membership | Diformalkan Modul 4–5 |
