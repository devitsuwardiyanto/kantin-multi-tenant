<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entri buku besar tenant — APPEND-ONLY. Saldo diturunkan dari akumulasi delta
 * available/held; koreksi keuangan memakai entri reversal, bukan edit historis.
 * idempotency_key UNIQUE menjamin satu efek per operasi (dedup settlement/reversal).
 *
 * type: sale_credit|commission_debit|hold|release|withdrawal_debit|reversal
 */
class LedgerEntry extends Model
{
    use BelongsToTenant;

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'available_delta' => 'integer',
            'held_delta' => 'integer',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
