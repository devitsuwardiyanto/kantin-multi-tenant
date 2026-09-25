<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Pembayaran satu order (platform-scoped; satu payment per order). Uang = integer Rupiah.
 * idempotency_key & payment_reference unik. Status: pending|paid|failed|expired|refunded|needs_review.
 * needs_review diselesaikan pengelola kantin (review_outcome: accepted|refunded) — UC-08 alur 3a.
 */
class Payment extends Model
{
    protected $fillable = ['status'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'settled_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<PaymentAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    /** @return HasOne<PaymentAttempt, $this> */
    public function latestAttempt(): HasOne
    {
        return $this->hasOne(PaymentAttempt::class)->latestOfMany();
    }

    /** @return HasMany<PaymentEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
