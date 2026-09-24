<?php

namespace App\Modules\Ordering\Console;

use App\Modules\Ordering\Services\PreOrderScheduler;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * UC-06 alur 5: dijalankan penjadwal setiap menit (toleransi ± 1 menit). Melepas pesanan
 * terjadwal yang SUDAH DIBAYAR ke antrean dapur saat release_at (waktu ambil − penyiapan) tiba.
 */
#[Signature('ordering:release-scheduled')]
#[Description('Lepaskan pesanan pre-order terjadwal ke antrean dapur')]
class ReleaseScheduledOrders extends Command
{
    public function handle(PreOrderScheduler $scheduler): int
    {
        $released = $scheduler->releaseDue();

        $this->components->info("{$released} pesanan terjadwal dilepas ke antrean dapur.");

        return self::SUCCESS;
    }
}
