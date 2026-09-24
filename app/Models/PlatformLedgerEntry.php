<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entri buku besar pengelola kantin — APPEND-ONLY (UC-10 langkah 4): komisi, pajak, biaya
 * layanan, dan selisih pembulatan tiap pembayaran; koreksi memakai entri reversal.
 * idempotency_key UNIQUE menjamin satu efek per operasi.
 */
class PlatformLedgerEntry extends Model
{
    protected $fillable = [];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    /** @return BelongsTo<Canteen, $this> */
    public function canteen(): BelongsTo
    {
        return $this->belongsTo(Canteen::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
