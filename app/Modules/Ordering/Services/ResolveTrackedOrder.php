<?php

namespace App\Modules\Ordering\Services;

use App\Models\Order;
use App\Support\Tokens\OpaqueToken;
use Illuminate\Http\Request;

/**
 * Mengembalikan Order dari cookie pelacakan opaque (`order_tracking`) — di-hash lalu dicari.
 * Sumber kepercayaan halaman status & pembayaran pelanggan anonim (bukan input klien).
 */
class ResolveTrackedOrder
{
    public const COOKIE = 'order_tracking';

    public function current(Request $request): ?Order
    {
        $token = $request->cookie(self::COOKIE);

        return is_string($token) && $token !== '' ? $this->fromToken($token) : null;
    }

    public function fromToken(string $plainToken): ?Order
    {
        return Order::query()
            ->where('tracking_token_hash', OpaqueToken::hash($plainToken))
            ->first();
    }
}
