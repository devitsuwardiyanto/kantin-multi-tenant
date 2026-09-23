<?php

namespace App\Modules\Admin\Services;

use App\Models\DiningTable;
use App\Models\TableQrToken;
use App\Support\Tokens\OpaqueToken;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Menerbitkan & merotasi token QR meja. Satu token aktif per meja: rotasi mencabut token
 * aktif lama dalam transaksi (row lock) lalu menerbitkan yang baru. Token mentah dikembalikan
 * SEKALI untuk membentuk URL/QR; server hanya menyimpan hash-nya.
 */
final class QrTokenService
{
    public function __construct(private AuditLogger $audit) {}

    public function issue(DiningTable $table, ?CarbonInterface $expiresAt = null): string
    {
        return DB::transaction(function () use ($table, $expiresAt): string {
            $locked = DiningTable::query()->lockForUpdate()->findOrFail($table->id);

            TableQrToken::query()
                ->where('dining_table_id', $locked->id)
                ->active()
                ->update(['status' => 'revoked', 'revoked_at' => now()]);

            $token = OpaqueToken::issue(32);
            $qr = new TableQrToken;
            $qr->forceFill([
                'dining_table_id' => $locked->id,
                'token_hash' => $token['hash'],
                'status' => 'active',
                'issued_at' => now(),
                'expires_at' => $expiresAt,
                'revoked_at' => null,
            ])->save();

            $this->audit->record('table_qr_token', $qr->id, 'issued',
                null, ['dining_table_id' => $locked->id], null, $locked->canteen_id);

            return $token['plain'];
        });
    }

    /** Rotasi = terbitkan token baru (otomatis mencabut yang aktif). */
    public function rotate(DiningTable $table): string
    {
        return $this->issue($table);
    }
}
