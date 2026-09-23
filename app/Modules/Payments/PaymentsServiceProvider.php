<?php

namespace App\Modules\Payments;

use App\Modules\ModuleServiceProvider;

/**
 * Modul: Payments (alias `payments`).
 * Tanggung jawab: Kontrak PaymentGateway, adapter, webhook, settlement, split ledger, outbox (Modul 10-11).
 *
 * Titik perakitan modul: binding container di register(); route (routes/*.php), view
 * (`payments::`) dan komponen Livewire (`<livewire:payments::...>`) dimuat oleh
 * ModuleServiceProvider::boot(). Batas antarmodul ditegakkan lewat kontrak & event,
 * bukan akses langsung tabel/controller modul lain.
 */
final class PaymentsServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        // Binding kontrak -> implementasi ditambahkan saat modul diimplementasikan.
    }

    protected function moduleAlias(): string
    {
        return 'payments';
    }
}
