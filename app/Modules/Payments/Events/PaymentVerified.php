<?php

namespace App\Modules\Payments\Events;

use App\Models\Payment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * UC-08 langkah 6–7: pembayaran terverifikasi dan dana telah dipecah. Didispatch SETELAH
 * transaksi basis data commit, sehingga pendengar (siaran KDS UC-15 dan notifikasi UC-12 di
 * Pertemuan 12) tidak pernah melihat pembayaran yang kemudian di-rollback.
 */
class PaymentVerified implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Payment $payment) {}
}
