# ADR-0001 — Struktur repository & workflow GitHub

- Status: Diterima
- Tanggal: 2026-08-16
- Konteks: Prompt tugas (otoritas tertinggi) menetapkan satu aplikasi Laravel di root repo dan
  workflow `branch → PR → CI → merge → annotated tag → release`. Modul v7 masih menuliskan strategi
  lama `main → develop → feat/week-NN` tanpa PR/tag/release (lihat DOC-01-003).

## Keputusan
1. Satu repository Git, satu aplikasi Laravel di **root** (bukan folder `modul-1..14`, bukan nested repo).
2. `main` adalah satu-satunya branch integrasi; selalu mewakili pertemuan terakhir yang **LULUS**.
3. Tiap pertemuan: branch `feat/pertemuan-NN-slug` dari `main` → PR ke `main` → CI required checks →
   merge → annotated tag `pertemuan-NN` pada commit merge → GitHub Release `Pertemuan NN — judul`.
4. Artefak sumber (SRS, ERD, mockup, modul) disimpan sekali di `references/` dan tidak diedit.
   Revisi dokumen modul ditulis kumulatif ke berkas versi baru (lihat DOC-01-001), bukan ke v7.
5. Beberapa pertemuan dibuka berdampingan hanya via `git worktree` (bukan copy manual).

## Konsekuensi
- Menggantikan bagian "Strategi Branch dan Evidence" pada modul (revisi DOC-01-003).
- Operasi eksternal (push/PR/CI/tag-push/release) menunggu identitas `GITHUB_REPOSITORY` yang sah;
  tidak boleh menebak target remote.
