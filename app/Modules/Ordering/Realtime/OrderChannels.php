<?php

namespace App\Modules\Ordering\Realtime;

use App\Models\Order;
use App\Support\Tokens\OpaqueToken;

/**
 * UC-09: channel privat pelacakan per pesanan `order.{public_id}` (UUID, bukan ID internal).
 * Otorisasi mensyaratkan DUA hal: sesi anonim pemilik pesanan DAN token pelacakan (cookie
 * order_tracking, ≥ 256 bit) yang cocok dengan hash tersimpan.
 */
final class OrderChannels
{
    /** Pola channel (didaftarkan OrderingServiceProvider). */
    public const PATTERN = 'order.{publicId}';

    public static function order(string $publicId): string
    {
        return 'order.'.$publicId;
    }

    public static function canTrack(CustomerChannelUser $user, string $publicId, ?string $trackingToken): bool
    {
        if (! is_string($trackingToken) || $trackingToken === '') {
            return false;
        }

        return Order::query()
            ->where('public_id', $publicId)
            ->where('customer_session_id', $user->customerSessionId)
            ->where('tracking_token_hash', OpaqueToken::hash($trackingToken))
            ->exists();
    }
}
