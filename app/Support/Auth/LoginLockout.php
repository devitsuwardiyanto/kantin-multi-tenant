<?php

namespace App\Support\Auth;

use Illuminate\Support\Facades\Cache;
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
        return $this->lockedUntil($email) > now()->getTimestamp();
    }

    /**
     * Sisa waktu kunci dalam menit (dibulatkan ke atas) untuk pesan kepada pengguna.
     */
    public function minutesRemaining(string $email): int
    {
        return max(1, (int) ceil(($this->lockedUntil($email) - now()->getTimestamp()) / 60));
    }

    /**
     * Catat satu kegagalan; kembalikan true bila kegagalan ini memicu penguncian.
     * Jendela dan masa kunci dihitung dari timestamp tersimpan (bukan TTL cache), sehingga
     * perilakunya sama pada store array maupun Redis. TTL hanya untuk membersihkan kunci lama.
     */
    public function recordFailure(string $email): bool
    {
        $now = now()->getTimestamp();
        /** @var array{count: int, started_at: int}|null $window */
        $window = Cache::get($this->counterKey($email));

        if ($window === null || $now - $window['started_at'] >= self::FAILURE_WINDOW_SECONDS) {
            $window = ['count' => 0, 'started_at' => $now];
        }

        $window['count']++;

        if ($window['count'] < self::MAX_FAILURES) {
            Cache::put($this->counterKey($email), $window, self::FAILURE_WINDOW_SECONDS);

            return false;
        }

        Cache::put($this->lockKey($email), $now + self::LOCK_SECONDS, self::LOCK_SECONDS);
        Cache::forget($this->counterKey($email));

        return true;
    }

    public function clear(string $email): void
    {
        Cache::forget($this->counterKey($email));
        Cache::forget($this->lockKey($email));
    }

    private function lockedUntil(string $email): int
    {
        return (int) Cache::get($this->lockKey($email), 0);
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
