# Pertemuan 01 — Security Verification

Pertemuan 1 = fondasi lingkungan; isolasi tenant & authorization matrix diperkenalkan Modul 4.
Pemeriksaan keamanan yang relevan pada tahap ini:

| Item | Status | Bukti |
|---|---|---|
| `.env` tidak masuk Git | ✅ | `.gitignore` mengabaikan `.env`, `.env.testing`; `git status`/`git diff --cached` bersih dari secret |
| Repo publik tanpa secret | ✅ | Hanya `.env.example` (placeholder) yang di-commit; password DB/REVERB nyata hanya di `.env` lokal |
| Dependency advisories | ✅ | `composer audit` & `npm audit` → 0 kerentanan |
| `APP_DEBUG` di produksi | ✅ (kebijakan) | ADR-0003: `APP_DEBUG=false` di produksi; lokal `true` |
| Branch protection `main` | ✅ | Wajib PR, linear history, tanpa force-push/delete (repo dijadikan publik agar fitur aktif) |
| Auth baseline | ✅ | Fortify login/register/2FA/passkey + session; enforcement server-side bawaan starter kit |

## Cross-tenant / IDOR
- Belum berlaku (belum ada entitas tenant). Matriks cross-tenant wajib mulai Modul 4 dan dijaga hijau seterusnya.
