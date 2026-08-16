<?php

namespace App\Modules\Ordering\Services;

use App\Models\Canteen;
use App\Models\CustomerSession;
use App\Models\TableQrToken;
use App\Support\Tokens\OpaqueToken;

/**
 * Menyelesaikan pemindaian QR: lookup hash token, validasi status/expiry/meja/canteen,
 * lalu membuat CustomerSession anonim terikat canteen+meja. Kegagalan bersifat generik
 * (tidak membocorkan apakah resource pernah ada).
 */
final class ResolveTableScan
{
    /**
     * @return array{session: CustomerSession, plain: string, canteen: Canteen}|null
     */
    public function resolve(string $plainToken): ?array
    {
        $qr = TableQrToken::query()
            ->where('token_hash', OpaqueToken::hash($plainToken))
            ->with('diningTable.canteen')
            ->first();

        if ($qr === null || ! $qr->isActive()) {
            return null;
        }

        $table = $qr->diningTable;
        if ($table === null || ! $table->isActive()) {
            return null;
        }

        $canteen = $table->canteen;
        if (! $canteen instanceof Canteen || $canteen->status !== 'active') {
            return null;
        }

        $sessionToken = OpaqueToken::issue(32);
        $session = new CustomerSession;
        $session->forceFill([
            'canteen_id' => $canteen->id,
            'dining_table_id' => $table->id,
            'qr_token_id' => $qr->id,
            'session_token_hash' => $sessionToken['hash'],
            'status' => 'active',
            'expires_at' => now()->addHours(4),
        ])->save();

        return ['session' => $session, 'plain' => $sessionToken['plain'], 'canteen' => $canteen];
    }
}
