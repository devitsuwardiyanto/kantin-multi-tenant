<?php

namespace App\Support\Tokens;

use InvalidArgumentException;

/**
 * Token opaque publik (QR/session): CSPRNG, base64url, disimpan sebagai SHA-256 raw (BINARY 32).
 * Token mentah hanya diberikan sekali ke klien; server menyimpan hash-nya.
 */
final class OpaqueToken
{
    /**
     * @return array{plain: string, hash: string}
     */
    public static function issue(int $bytes = 32): array
    {
        if ($bytes < 16) {
            throw new InvalidArgumentException('Token minimal 128 bit (16 byte).');
        }

        $plain = rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');

        return ['plain' => $plain, 'hash' => hash('sha256', $plain, true)];
    }

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain, true);
    }
}
