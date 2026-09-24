<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rincian pemecahan dana satu pembayaran (UC-10 langkah 6): satu baris per tenant dan satu
 * baris untuk pengelola kantin. Jumlah `amount` seluruh baris = nominal pembayaran (selisih 0).
 * Ditulis sekali oleh SettlePayment; tidak diubah (dasar rekonsiliasi UC-18).
 *
 * @property int $id
 * @property int $payment_id
 * @property int|null $tenant_id
 * @property string $recipient
 * @property int $amount
 */
class PaymentAllocation extends Model
{
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'subtotal_amount' => 'integer',
            'commission_rate_snapshot' => 'decimal:4',
            'commission_amount' => 'integer',
            'tax_amount' => 'integer',
            'service_fee_amount' => 'integer',
            'rounding_amount' => 'integer',
            'amount' => 'integer',
        ];
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
