# Pertemuan 05 — Known Issues

| # | Severity | Isu | Status |
|---|---|---|---|
| 1 | Info | Anti-overlap komisi via generated column + UNIQUE (bukan trigger) | DOC-05-001; setara & portabel |
| 2 | Info | UI admin memakai controller+Blade (bukan Livewire penuh) | Cukup & teruji; Livewire opsional |
| 3 | Info | Verifikasi rekening (Tahap 5) dibatasi manager/finance canteen | Sesuai policy |
| 4 | Low | Overlap komisi penuh (bukan hanya satu-aktif) diserahkan ke service | Guard DB menjaga invariant utama |
