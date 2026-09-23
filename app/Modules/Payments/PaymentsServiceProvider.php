<?php

namespace App\Modules\Payments;

use App\Modules\ModuleServiceProvider;
use App\Modules\Payments\Console\ReprocessFailedSettlements;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Gateways\FakeQrisGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

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

    public function boot(): void
    {
        parent::boot();

        // Webhook provider tak mengirim token CSRF; keaslian dijamin signature HMAC atas raw body.
        PreventRequestForgery::except('webhooks/*');

        // UC-10 alur 3a: proses ulang settlement yang gagal.
        if ($this->app->runningInConsole()) {
            $this->commands([ReprocessFailedSettlements::class]);
        }

        // Webhook pembayaran: batasi laju per IP (dedup + signature tetap lapis utama; Modul 14).
        RateLimiter::for('qris-webhook', fn (Request $request): Limit => Limit::perMinute(120)->by($request->ip() ?? 'unknown'));
    }

    protected function moduleAlias(): string
    {
        return 'payments';
    }
}
