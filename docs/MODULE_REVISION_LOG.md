# Module Revision Log

Revisi dokumen modul hanya dilakukan bila ada bukti (lihat protokol revisi pada prompt tugas).
Base dokumen aktual: `Modul_..._v7.docx`. Target revisi kumulatif: **belum dikonfirmasi** — lihat DOC-01-001.

Status: `DIUSULKAN` (baru catatan) · `DITERAPKAN` (kode teruji + DOCX diperbarui + inspeksi visual).

| ID | Modul/Tahap | Temuan | Bukti | Resolusi | Status |
|---|---|---|---|---|---|
| DOC-01-001 | Global / parameter | Prompt menyebut base `v4` → revisi `v5`, tetapi berkas nyata di `references/` adalah **v7** dan tidak ada v4/v5. | `ls references/` hanya memuat `..._v7.docx`. | Perlakukan **v7** sebagai base. Nama berkas revisi kumulatif menunggu konfirmasi pengguna (usul: `v8`). | DIUSULKAN |
| DOC-01-002 | Global / basis desain | Modul menyatakan basis "SRS v1.1", input tersedia adalah **SRS v2**. | Header modul baris "Basis desain: SRS v1.1"; berkas `SRS_..._ISO29148 v2.docx`. | Audit diff SRS v1.1→v2 sebelum Modul 3 (data). Perbarui RTM & ketertelusuran ke SRS v2 (otoritas lebih tinggi). | DIUSULKAN |
| DOC-01-003 | Global / strategi Git | Modul: `main → develop → feat/week-NN`, evidence minimal, tanpa PR/tag/release. Prompt (otoritas lebih tinggi): `main` + `feat/pertemuan-NN-slug` + annotated tag `pertemuan-NN` + GitHub Release + CI required checks. | Bagian "Strategi Branch dan Evidence" modul vs kontrak GitHub pada prompt. | Ganti strategi ke model prompt; Tahap 7 tiap modul mencakup PR→CI→merge→tag→release; `git worktree` untuk membuka versi berdampingan. | DIUSULKAN |
| DOC-01-004 | Modul 1 / Tahap 4 & cuplikan | Modul memakai `DB_PORT=3307`, `DB_DATABASE=kantin_multi_tenant`, `REDIS` port `6380`. Environment nyata (kredensial pengguna, terverifikasi live): `DB_PORT=3306`, `DB_DATABASE=kantin`, `DB_USERNAME=kantin`, Redis `6379`. | `mysql -h127.0.0.1 -P3306 -ukantin` → MariaDB 11.4.5, db `kantin` ada; `redis-cli -p 6379 ping` → PONG; `-p 6380` connection refused. | Gunakan konfigurasi nyata pengguna (otoritas tertinggi). Lihat [ADR-0002](adr/0002-database-redis-connection.md). Perbarui Tahap 4, cuplikan `.env.example`, tabel troubleshooting port. | DIUSULKAN |
| DOC-01-005 | Modul 1 / intro vs Tahap 4 | Zona waktu tidak konsisten: intro menyebut `Asia/Jakarta`, Tahap 4 menyebut `Asia/Makassar`. | Baris intro §mode produksi vs Tahap 4. | Simpan UTC di DB; presentasi `Asia/Jakarta` (WIB). Lihat [ADR-0003](adr/0003-timezone.md). Seragamkan teks modul. | DIUSULKAN |

> Catatan: seluruh temuan di atas masih `DIUSULKAN` (belum mengedit DOCX). Penerapan ke DOCX v(8) dilakukan setelah kode Modul terkait terbukti hijau, mengikuti protokol revisi (langkah 3–9).
