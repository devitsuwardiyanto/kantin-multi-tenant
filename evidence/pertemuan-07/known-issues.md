# Pertemuan 07 — Known Issues

| # | Severity | Isu | Status |
|---|---|---|---|
| 1 | Info | Upload foto menu + WebP (intervention/image) belum dipasang | DOC-07-001; menunggu persetujuan dependency + migrasi kolom path |
| 2 | Info | UI modifier detail (modal min/max di katalog) minimal | Data+relasi+validasi ada; UI modifier diperkaya saat cart (Modul 8) |
| 3 | Info | Logika Livewire SFC (.blade.php) tak dianalisis PHPStan | Logika inti di service (tercek); komponen tipis + teruji via Livewire::test |
| 4 | Low | preventLazyLoading belum diaktifkan (deteksi N+1) | Diuji manual via query count; hardening Modul 14 |
