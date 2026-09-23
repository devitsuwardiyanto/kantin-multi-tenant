<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Percobaan pembayaran (satu tagihan QRIS). provider_reference unik; qris_payload = EMVCo
 * dinamis. Status: created|pending|success|failed|expired.
 *
 * @property CarbonImmutable|null $expires_at
 */
class PaymentAttempt extends Model
{
    protected $fillable = ['status'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
