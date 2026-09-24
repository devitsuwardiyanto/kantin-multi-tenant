<?php

namespace App\Support\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Penguncian akun UC-19 (alur 2b): lebih dari 5 kali gagal masuk dalam 10 menit mengunci
 * akun selama 15 menit. Kunci dihitung per AKUN (surel ternormalisasi, di-hash), bukan per IP,
 * sehingga berganti IP tidak mereset penghitung. Penyimpanan: cache store aplikasi (Redis).
 */
final class LoginLockout
{
    public const MAX_FAILURES = 5;

    public const FAILURE_WINDOW_SECONDS = 600;

    public const LOCK_SECONDS = 900;

    public function isLocked(string $email): bool
    {
        return Cache::has($this->lockKey($email));
    }

    /**
     * Sisa waktu kunci dalam menit (dibulatkan ke atas) untuk pesan kepada pengguna.
     */
    public function minutesRemaining(string $email): int
    {
        $until = (int) Cache::get($this->lockKey($email), 0);

        return max(1, (int) ceil(($until - now()->getTimestamp()) / 60));
    }

    /**
     * Catat satu kegagalan; kembalikan true bila kegagalan ini memicu penguncian.
     */
    public function recordFailure(string $email): bool
    {
        $counterKey = $this->counterKey($email);
        RateLimiter::hit($counterKey, self::FAILURE_WINDOW_SECONDS);

        if (RateLimiter::attempts($counterKey) < self::MAX_FAILURES) {
            return false;
        }

        Cache::put($this->lockKey($email), now()->addSeconds(self::LOCK_SECONDS)->getTimestamp(), self::LOCK_SECONDS);
        RateLimiter::clear($counterKey);

        return true;
    }

    public function clear(string $email): void
    {
        RateLimiter::clear($this->counterKey($email));
        Cache::forget($this->lockKey($email));
    }

    private function counterKey(string $email): string
    {
        return 'login-failures:'.$this->fingerprint($email);
    }

    private function lockKey(string $email): string
    {
        return 'login-lock:'.$this->fingerprint($email);
    }

    private function fingerprint(string $email): string
    {
        return hash('sha256', Str::lower(trim($email)));
    }
}
