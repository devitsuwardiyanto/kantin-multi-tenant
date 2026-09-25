<?php

namespace App\Modules\Ordering;

use App\Modules\Kitchen\Events\TenantOrderStatusChanged;
use App\Modules\ModuleServiceProvider;
use App\Modules\Ordering\Console\ReleaseScheduledOrders;
use App\Modules\Ordering\Contracts\NotificationGateway;
use App\Modules\Ordering\Gateways\FakeWhatsAppGateway;
use App\Modules\Ordering\Listeners\NotifyCustomerOfOrderEvents;
use App\Modules\Ordering\Realtime\CustomerChannelUser;
use App\Modules\Ordering\Realtime\OrderChannels;
use App\Modules\Payments\Events\PaymentVerified;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Modul: Ordering (alias `ordering`).
 * Tanggung jawab: Keranjang (Redis), checkout atomik, order induk, snapshot (Modul 8-9). Pemilik route customer.
 *
 * Titik perakitan modul: binding container di register(); route (routes/*.php), view
 * (`ordering::`) dan komponen Livewire (`<livewire:ordering::...>`) dimuat oleh
 * ModuleServiceProvider::boot(). Batas antarmodul ditegakkan lewat kontrak & event,
 * bukan akses langsung tabel/controller modul lain.
 */
final class OrderingServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        // UC-12: HANYA SATU saluran notifikasi di-bind (sandbox: WhatsApp tiruan).
        $this->app->bind(NotificationGateway::class, fn (): FakeWhatsAppGateway => new FakeWhatsAppGateway(
            unavailable: (bool) config('services.whatsapp.fake_unavailable'),
        ));
    }

    public function boot(): void
    {
        parent::boot();

        // Rate limiter milik modul: entry QR meja publik (mengurangi brute force token QR).
        RateLimiter::for('qr-scan', fn (Request $request): Limit => Limit::perMinute(30)->by($request->ip() ?? 'unknown'));

        // Token pelacakan pesanan = opaque 256-bit (disimpan sebagai hash SHA-256); enkripsi
        // cookie tak menambah kerahasiaan, sedangkan pelacakan stateless butuh nilai stabil.
        EncryptCookies::except('order_tracking');

        // UC-06: pelepasan pre-order ke antrean dapur, dicek setiap menit (toleransi ± 1 menit).
        if ($this->app->runningInConsole()) {
            $this->commands([ReleaseScheduledOrders::class]);
        }
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('ordering:release-scheduled')->everyMinute()->withoutOverlapping();
        });

        // UC-09: channel privat pelacakan pesanan (otorisasi sesi anonim + token pelacakan).
        Broadcast::channel(OrderChannels::PATTERN, fn (mixed $user, string $publicId): bool => $user instanceof CustomerChannelUser
            && OrderChannels::canTrack($user, $publicId, is_string($token = request()->cookie('order_tracking')) ? $token : null));

        // UC-12 + UC-09: reaksi terhadap event modul Payments dan Kitchen.
        Event::listen(PaymentVerified::class, [NotifyCustomerOfOrderEvents::class, 'onPaymentVerified']);
        Event::listen(TenantOrderStatusChanged::class, [NotifyCustomerOfOrderEvents::class, 'onTenantOrderStatusChanged']);
    }

    protected function moduleAlias(): string
    {
        return 'ordering';
    }
}
