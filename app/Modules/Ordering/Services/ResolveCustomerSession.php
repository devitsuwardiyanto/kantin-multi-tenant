<?php

namespace App\Modules\Ordering\Services;

use App\Models\CustomerSession;
use App\Support\Tokens\OpaqueToken;
use Illuminate\Http\Request;

/**
 * Mengembalikan CustomerSession AKTIF dari cookie HttpOnly `customer_session`. Token mentah
 * di cookie di-hash lalu dicari; hanya sesi berstatus aktif & belum kedaluwarsa yang diakui.
 * Sumber kepercayaan keranjang berasal dari sini — bukan dari prop/komponen sisi klien.
 */
class ResolveCustomerSession
{
    public const COOKIE = 'customer_session';

    public function current(Request $request): ?CustomerSession
    {
        $token = $request->cookie(self::COOKIE);

        return is_string($token) && $token !== '' ? $this->fromToken($token) : null;
    }

    public function fromToken(string $plainToken): ?CustomerSession
    {
        $session = CustomerSession::query()
            ->where('session_token_hash', OpaqueToken::hash($plainToken))
            ->first();

        if ($session === null || ! $session->isActive()) {
            return null;
        }

        return $session;
    }
}
