<?php

use App\Models\Order;
use App\Models\TenantOrder;
use App\Modules\Kitchen\Services\KitchenService;
use App\Modules\Kitchen\Services\WaitTimeEstimator;
use App\Modules\Ordering\Realtime\OrderChannels;
use App\Modules\Ordering\Services\ResolveTrackedOrder;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * UC-09 Lacak Status Pesanan. Order diambil dari cookie pelacakan tepercaya; status setiap
 * sub-pesanan diperbarui lewat channel privat `order.{public_id}` (diotorisasi token + sesi
 * anonim) dan, bila WebSocket gagal, polling cadangan 15 detik (alur 3a). Estimasi dari UC-11.
 */
new class extends Component
{
    public string $canteenSlug = '';

    /** Langkah pelacakan pelanggan → status sub-pesanan yang sudah melewati langkah tsb. */
    private const STEPS = [
        'Diterima' => ['accepted', 'preparing', 'ready', 'completed'],
        'Dimasak' => ['preparing', 'ready', 'completed'],
        'Siap' => ['ready', 'completed'],
        'Selesai' => ['completed'],
    ];

    public function mount(string $canteenSlug): void
    {
        $this->canteenSlug = $canteenSlug;
    }

    #[Computed]
    public function order(): ?Order
    {
        return app(ResolveTrackedOrder::class)->current(request())
            ?->load(['tenantOrders.tenant:id,display_name', 'tenantOrders.items', 'payment']);
    }

    /**
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        $publicId = $this->order?->public_id;

        return $publicId ? ['echo-private:'.OrderChannels::order((string) $publicId).',.OrderTrackingUpdated' => 'refresh'] : [];
    }

    public function refresh(): void
    {
        unset($this->order);
    }

    /**
     * @return array<string, list<string>>
     */
    public function steps(): array
    {
        return self::STEPS;
    }

    /** Label status sub-pesanan untuk pelanggan. */
    public function badge(TenantOrder $tenantOrder, Order $order): string
    {
        return match (true) {
            $order->status === 'awaiting_payment' => 'MENUNGGU PEMBAYARAN',
            $order->status === 'cancelled' || $tenantOrder->status === 'cancelled' => 'DIBATALKAN',
            $tenantOrder->status === 'scheduled' => 'TERJADWAL',
            $tenantOrder->status === 'pending' => 'MENUNGGU DAPUR',
            default => ['accepted' => 'DITERIMA', 'preparing' => 'DIMASAK', 'ready' => 'SIAP DIAMBIL', 'completed' => 'SELESAI'][$tenantOrder->status] ?? strtoupper($tenantOrder->status),
        };
    }

    /** Keterangan per sub-pesanan, termasuk estimasi waktu tunggu (include UC-11). */
    public function detail(TenantOrder $tenantOrder, Order $order): string
    {
        $tz = (string) config('app.display_timezone');

        return match (true) {
            $order->status !== 'paid' => '',
            $tenantOrder->status === 'cancelled' => 'dibatalkan ('.(KitchenService::CANCEL_REASONS[$tenantOrder->cancel_reason ?? ''] ?? 'oleh tenant').') · dana dikembalikan pengelola',
            $tenantOrder->status === 'scheduled' => 'ambil pukul '.$tenantOrder->scheduled_at?->setTimezone($tz)->format('H.i'),
            $tenantOrder->status === 'ready' => 'silakan ambil di konter',
            $tenantOrder->status === 'completed' => 'selamat menikmati',
            default => 'estimasi '.app(WaitTimeEstimator::class)->label($tenantOrder->tenant, (float) $tenantOrder->items->sum('prep_minutes_snapshot')).' lagi',
        };
    }
};
?>

@php($order = $this->order)
@php($tz = (string) config('app.display_timezone'))
<section id="lacak" aria-label="Lacak pesanan" data-test="order-tracker"
    x-data="{
        connected: false,
        init() {
            const connection = window.Echo?.connector?.pusher?.connection;
            if (connection) {
                this.connected = connection.state === 'connected';
                connection.bind('state_change', ({ current }) => { this.connected = current === 'connected'; });
            }
            // UC-09 alur 3a: polling cadangan setiap 15 detik selama WebSocket tidak terhubung.
            setInterval(() => { if (! this.connected) { $wire.$refresh(); } }, 15000);
        },
    }">
    @if ($order)
        <div class="flex items-start justify-between gap-3 border-b-2 border-zinc-900 pb-3 dark:border-zinc-100">
            <div>
                <h2 class="text-2xl font-extrabold">Pesanan #{{ $order->order_number }}</h2>
                <p class="text-sm text-zinc-500">
                    {{ $order->table_snapshot['label'] ?? 'Ambil di konter' }}
                    @if ($order->payment?->settled_at) · Dibayar {{ $order->payment->settled_at->setTimezone($tz)->format('H.i') }} @endif
                    · Total Rp{{ number_format($order->grand_total_amount, 0, ',', '.') }}
                </p>
                <p class="text-sm text-zinc-500" data-test="service-mode">Mode: {{ $order->service_mode === 'pickup' ? 'Pesan dulu / Pick-up' : 'Makan di tempat' }}@if ($order->customer_snapshot['name'] ?? null) · a.n. {{ $order->customer_snapshot['name'] }}@endif</p>
            </div>
            <span class="flex shrink-0 items-center gap-1.5 text-sm font-extrabold" data-test="realtime">
                <span class="size-2.5 rounded-full" :class="connected ? 'bg-green-600' : 'bg-amber-500'"></span>
                <span :class="connected ? 'text-green-700' : 'text-amber-700'" x-text="connected ? 'REAL-TIME' : 'POLLING 15 DTK'">POLLING 15 DTK</span>
            </span>
        </div>

        <div class="mt-4 space-y-4">
            @foreach ($order->tenantOrders as $tenantOrder)
                @php($badge = $this->badge($tenantOrder, $order))
                <article wire:key="track-{{ $tenantOrder->id }}" class="border-2 border-zinc-900 dark:border-zinc-100" data-test="track-tenant">
                    <div class="flex items-center justify-between border-b-2 border-zinc-900 px-4 py-3 dark:border-zinc-100">
                        <h3 class="font-extrabold uppercase">{{ $tenantOrder->tenant?->display_name }}</h3>
                        <span @class(['px-2 py-1 text-xs font-bold tracking-wider text-white',
                            'bg-green-700' => in_array($badge, ['SIAP DIAMBIL', 'SELESAI'], true),
                            'bg-red-600' => in_array($badge, ['DIMASAK', 'DIBATALKAN'], true),
                            'bg-zinc-900 dark:bg-zinc-100 dark:text-zinc-900' => ! in_array($badge, ['SIAP DIAMBIL', 'SELESAI', 'DIMASAK', 'DIBATALKAN'], true)])>{{ $badge }}</span>
                    </div>
                    <div class="px-4 py-3">
                        @if ($order->status === 'paid' && $tenantOrder->status !== 'cancelled')
                            <ol class="grid grid-cols-4 text-[11px] font-bold tracking-wider" aria-label="Tahapan pesanan">
                                @foreach ($this->steps() as $step => $reached)
                                    @php($done = in_array($tenantOrder->status, $reached, true))
                                    <li @class(['flex flex-col gap-1', 'text-zinc-400' => ! $done])>
                                        <span @class(['flex size-7 items-center justify-center border-2 text-white', 'border-zinc-900 bg-zinc-900 dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900' => $done, 'border-zinc-300' => ! $done])>{{ $done ? '✓' : '' }}</span>
                                        {{ strtoupper($step) }}
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                        <p class="mt-2 text-sm">
                            {{ $tenantOrder->items->map(fn ($item) => $item->quantity.'× '.$item->name_snapshot)->implode(', ') }}
                            @php($detail = $this->detail($tenantOrder, $order))
                            @if ($detail !== '') · <span class="font-bold" data-test="track-detail">{{ $detail }}</span> @endif
                        </p>
                    </div>
                </article>
            @endforeach
        </div>
        <p class="mt-3 text-xs text-zinc-500">Pembaruan via WebSocket ≤ 3 dtk; bila terputus, polling tiap 15 dtk. Notifikasi juga dikirim ke WhatsApp.</p>
    @endif
</section>
