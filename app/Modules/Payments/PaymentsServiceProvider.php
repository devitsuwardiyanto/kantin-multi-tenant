<?php

namespace App\Modules\Payments;

use App\Modules\ModuleServiceProvider;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Gateways\FakeQrisGateway;

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
        // HANYA SATU provider di-bind pada satu waktu. Sandbox memakai FakeQrisGateway;
        // mengganti ke provider nyata dilakukan dengan menukar binding tunggal ini.
        $this->app->bind(PaymentGateway::class, fn (): FakeQrisGateway => new FakeQrisGateway(
            unavailable: (bool) config('services.qris.fake_unavailable'),
        ));
    }

    protected function moduleAlias(): string
    {
        return 'payments';
    }
}
