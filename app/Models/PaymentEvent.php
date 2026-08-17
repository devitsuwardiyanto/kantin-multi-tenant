<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Event pembayaran dari provider — APPEND-ONLY (dedup via provider_event_id unik). Koreksi
 * dilakukan lewat event/reversal baru, bukan edit historis. result: verified|rejected|duplicate.
 */
class PaymentEvent extends Model
{
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'received_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
