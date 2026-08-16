<?php

namespace App\Modules\Payments;

use Illuminate\Support\ServiceProvider;

/**
 * Modul: Payments.
 * Tanggung jawab: Kontrak PaymentGateway, adapter, webhook, settlement, split ledger, outbox (Modul 10-11).
 *
 * Titik perakitan modul (modular monolith): binding container di register(),
 * route/event/policy di boot(). Batas antarmodul ditegakkan lewat kontrak & event,
 * bukan akses langsung tabel/controller modul lain.
 */
final class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Binding kontrak -> implementasi ditambahkan saat modul diimplementasikan.
    }

    public function boot(): void
    {
        //
    }
}
