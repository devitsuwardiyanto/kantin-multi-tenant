<?php

namespace App\Modules\Payments\Support;

/**
 * Verifikasi signature webhook: HMAC-SHA256 atas RAW BODY memakai secret bersama, dibandingkan
 * dengan hash_equals (konstan-waktu). Fail-closed: tanpa secret atau signature → ditolak.
 */
final class WebhookSignatureVerifier
{
    public function verify(string $rawBody, ?string $signature): bool
    {
        $secret = (string) config('services.qris.webhook_secret', '');
        if ($secret === '' || $signature === null || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signature);
    }

    public function sign(string $rawBody): string
    {
        return hash_hmac('sha256', $rawBody, (string) config('services.qris.webhook_secret', ''));
    }
}
