<?php

namespace App\Modules\Payments\Console;

use App\Models\Payment;
use App\Modules\Payments\Services\ProcessPaymentWebhook;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * UC-10 alur 3a: pembayaran yang settlement-nya gagal (seluruh entri di-rollback) ditandai
 * `settlement_failed`; perintah ini memprosesnya ulang dari payment_event terverifikasi terakhir.
 */
#[Signature('payments:reprocess-settlements')]
#[Description('Proses ulang pembayaran yang settlement-nya gagal')]
class ReprocessFailedSettlements extends Command
{
    public function handle(ProcessPaymentWebhook $processor): int
    {
        $done = 0;
        foreach (Payment::query()->where('status', 'settlement_failed')->pluck('id') as $paymentId) {
            $done += $processor->reprocess((int) $paymentId) ? 1 : 0;
        }

        $this->components->info("{$done} pembayaran diproses ulang.");

        return self::SUCCESS;
    }
}
