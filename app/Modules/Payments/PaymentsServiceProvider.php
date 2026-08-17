<?php

namespace App\Modules\Payments;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Gateways\FakeQrisGateway;
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
        // HANYA SATU provider di-bind pada satu waktu. Sandbox memakai FakeQrisGateway;
        // mengganti ke provider nyata dilakukan dengan menukar binding tunggal ini.
        $this->app->bind(PaymentGateway::class, FakeQrisGateway::class);
    }

    public function boot(): void
    {
        //
    }
}
