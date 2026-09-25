<?php

use App\Models\Tenant;
use App\Models\TenantOrder;
use App\Models\UserTenantRole;
use App\Modules\Kitchen\Exceptions\KitchenException;
use App\Modules\Kitchen\Services\KitchenService;
use App\Modules\Kitchen\Realtime\TenantChannels;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Kitchen Display System (KDS) tenant. tenantId prop publik TIDAK dipercaya: booted()
 * memverifikasi ulang membership DAN mengisi TenantContext tiap request (query ter-scope tenant).
 * Realtime via Echo private channel tenant.{id}.orders (Reverb); wire:poll sebagai fallback.
 * UC-15: kolom Baru / Diproses / Siap, Terima → Selesai → Diserahkan (≤ 2 ketukan per tahap),
 * batal dengan alasan (untuk refund pengelola), timer masak, penanda suara pesanan baru, dan
 * indikator koneksi (terputus → polling cadangan 10 detik).
 */
new class extends Component
{
    public int $tenantId = 0;

    /** @var array<string, list<string>> kolom KDS → status sub-pesanan */
    private const COLUMNS = [
        'Baru' => ['pending'],
        'Diproses' => ['accepted', 'preparing'],
        'Siap' => ['ready'],
    ];

    /** Kartu yang sedang dibuka formulir pembatalannya (alur 4a). */
    public ?int $cancelling = null;

    public string $cancelReason = '';

    public function mount(int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function booted(): void
    {
        $user = Auth::user();
        $isMember = $user !== null && UserTenantRole::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $this->tenantId)
            ->exists();
        abort_unless($isMember, 403);

        app(TenantContext::class)->set(Tenant::query()->findOrFail($this->tenantId));
    }

    /**
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        $channel = 'echo-private:'.TenantChannels::orders($this->tenantId);

        return [
            $channel.',.TenantOrderStatusChanged' => 'refresh',
            $channel.',.NewTenantOrderReceived' => 'handleNewOrder',
        ];
    }

    public function refresh(): void
    {
        unset($this->orders);
    }

    /** Pesanan baru: muat ulang antrean + penanda suara di peramban. */
    public function handleNewOrder(): void
    {
        unset($this->orders);
        $this->dispatch('kitchen-new-order');
    }

    #[Computed]
    public function tenantName(): string
    {
        return (string) Tenant::query()->whereKey($this->tenantId)->value('display_name');
    }

    /**
     * @return array<string, \Illuminate\Support\Collection<int, TenantOrder>>
     */
    #[Computed]
    public function orders(): array
    {
        $orders = TenantOrder::query()
            ->whereIn('status', array_merge(...array_values(self::COLUMNS)))
            ->whereHas('order', fn ($q) => $q->where('status', 'paid'))
            ->with(['order:id,order_number,table_snapshot,service_mode,updated_at', 'items:id,tenant_order_id,name_snapshot,quantity,note', 'items.modifiers:id,order_item_id,option_name_snapshot'])
            ->orderBy('created_at')
            ->get();

        $grouped = [];
        foreach (self::COLUMNS as $column => $statuses) {
            $grouped[$column] = $orders->whereIn('status', $statuses)->values();
        }

        return $grouped;
    }

    public function advance(int $tenantOrderId, string $target): void
    {
        $tenantOrder = TenantOrder::query()->findOrFail($tenantOrderId); // ter-scope tenant aktif

        try {
            app(KitchenService::class)->advance($tenantOrder, $target);
        } catch (KitchenException $e) {
            $this->addError('kitchen', $e->getMessage());
        }

        unset($this->orders);
    }

    public function startCancel(int $tenantOrderId): void
    {
        $this->cancelling = $tenantOrderId;
        $this->cancelReason = '';
        $this->resetErrorBag();
    }

    /** UC-15 alur 4a: batalkan dengan alasan; dicatat untuk pengembalian dana oleh pengelola. */
    public function confirmCancel(): void
    {
        if ($this->cancelling === null) {
            return;
        }

        $tenantOrder = TenantOrder::query()->findOrFail($this->cancelling);

        try {
            app(KitchenService::class)->advance($tenantOrder, 'cancelled', $this->cancelReason);
            $this->cancelling = null;
        } catch (KitchenException $e) {
            $this->addError('kitchen', $e->getMessage());
        }

        unset($this->orders);
    }

    /**
     * @return array<string, list<string>>
     */
    public function columns(): array
    {
        return self::COLUMNS;
    }
};
?>

@php($tz = (string) config('app.display_timezone'))
<div class="space-y-4" wire:poll.10s
    x-data="{
        connectionState: 'connecting',
        soundOn: false,
        get connected() { return this.connectionState === 'connected'; },
        initConnectionWatcher() {
            const connection = window.Echo?.connector?.pusher?.connection;
            if (! connection) { this.connectionState = 'unavailable'; return; }
            this.connectionState = connection.state;
            connection.bind('state_change', ({ current }) => { this.connectionState = current; });
        },
        playNewOrderChime() {
            if (! this.soundOn) { return; }
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = ctx.createOscillator();
                const gain = ctx.createGain();
                oscillator.connect(gain);
                gain.connect(ctx.destination);
                oscillator.frequency.value = 880;
                gain.gain.value = 0.15;
                oscillator.start();
                oscillator.stop(ctx.currentTime + 0.25);
            } catch (e) {
                // Web Audio tidak tersedia; abaikan.
            }
        },
    }"
    x-init="initConnectionWatcher()"
    x-on:kitchen-new-order.window="playNewOrderChime()">
    <div class="flex flex-wrap items-center gap-4 border-b-2 border-zinc-900 pb-3 dark:border-zinc-100">
        <span class="size-4 bg-red-600" aria-hidden="true"></span>
        <h2 class="text-lg font-extrabold uppercase tracking-wider">{{ $this->tenantName }} · Dapur</h2>
        <button type="button" x-on:click="soundOn = ! soundOn; if (soundOn) playNewOrderChime()" data-test="sound-toggle"
            class="border-2 border-zinc-900 px-3 py-1.5 text-sm font-bold dark:border-zinc-100">
            🔈 Suara: <span x-text="soundOn ? 'AKTIF' : 'MATI'">MATI</span>
        </button>
        <span class="ms-auto flex items-center gap-2 text-sm font-extrabold uppercase" data-test="connection">
            <span class="size-2.5 rounded-full" :class="connected ? 'bg-green-600' : 'bg-red-600'"></span>
            <span :class="connected ? 'text-green-700' : 'text-red-600'" x-text="connected ? 'Terhubung · WebSocket' : 'Terputus — polling 10 dtk'">Terputus — polling 10 dtk</span>
        </span>
    </div>
    <div x-show="! connected" class="bg-red-600 px-4 py-2 text-sm font-bold text-white" role="status">
        Koneksi real-time terputus. Menampilkan data dari polling cadangan setiap 10 detik.
    </div>

    @error('kitchen')
        <div class="bg-red-100 px-3 py-2 text-sm font-semibold text-red-800 dark:bg-red-900/40 dark:text-red-300" role="alert">{{ $message }}</div>
    @enderror

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        @foreach ($this->columns() as $column => $statuses)
            <section data-test="column-{{ strtolower($column) }}">
                <h3 class="mb-3 flex items-center justify-between border-b-2 border-zinc-900 pb-2 text-lg font-extrabold uppercase tracking-widest dark:border-zinc-100">
                    <span>{{ $column }}</span>
                    <span @class(['min-w-8 px-2 text-center text-base', 'bg-red-600 text-white' => $column === 'Baru', 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => $column === 'Diproses', 'border-2 border-zinc-900 dark:border-zinc-100' => $column === 'Siap'])>{{ $this->orders[$column]->count() }}</span>
                </h3>

                <div class="space-y-3">
                    @forelse ($this->orders[$column] as $tenantOrder)
                        @php($isNew = $tenantOrder->status === 'pending' && $tenantOrder->order->updated_at?->gt(now()->subMinutes(2)))
                        <article wire:key="to-{{ $tenantOrder->id }}" @class(['border-2 bg-white dark:bg-zinc-900', 'border-red-600' => $isNew, 'border-zinc-300 dark:border-zinc-700' => ! $isNew])>
                            <div class="flex items-baseline justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                                <p class="text-lg font-extrabold">
                                    #{{ $tenantOrder->order->order_number }} ·
                                    @if ($tenantOrder->scheduled_at)
                                        Pick-up {{ $tenantOrder->scheduled_at->setTimezone($tz)->format('H.i') }}
                                    @else
                                        {{ $tenantOrder->order->table_snapshot['label'] ?? 'Ambil di konter' }}
                                    @endif
                                </p>
                                @if ($tenantOrder->status === 'ready')
                                    <span class="bg-green-700 px-2 py-0.5 text-xs font-bold text-white">SIAP DIAMBIL</span>
                                @elseif ($tenantOrder->accepted_at)
                                    <span class="text-sm font-semibold text-zinc-500" data-test="timer">⏱ {{ sprintf('%02d.%02d', intdiv((int) $tenantOrder->accepted_at->diffInSeconds(now()), 60), (int) $tenantOrder->accepted_at->diffInSeconds(now()) % 60) }}</span>
                                @elseif ($isNew)
                                    <span class="text-sm font-bold text-red-600">baru saja</span>
                                @elseif ($tenantOrder->scheduled_at)
                                    <span class="text-sm font-semibold text-zinc-500">terjadwal</span>
                                @endif
                            </div>
                            <ul class="space-y-1 px-4 py-3 text-lg">
                                @foreach ($tenantOrder->items as $item)
                                    <li>{{ $item->quantity }}× {{ $item->name_snapshot }}
                                        @foreach ($item->modifiers as $modifier)<span class="block ps-4 text-base text-zinc-500">+ {{ $modifier->option_name_snapshot }}</span>@endforeach
                                        @if ($item->note)<span class="block ps-4 text-base text-zinc-500">— {{ $item->note }}</span>@endif
                                    </li>
                                @endforeach
                            </ul>

                            @if ($cancelling === $tenantOrder->id)
                                <div class="space-y-2 px-4 pb-4" data-test="cancel-form">
                                    <label for="cancelReason" class="text-sm font-bold">Alasan pembatalan</label>
                                    <select id="cancelReason" wire:model="cancelReason" class="w-full border-2 border-zinc-900 px-3 py-2 dark:border-zinc-100 dark:bg-zinc-900">
                                        <option value="">— pilih —</option>
                                        @foreach (\App\Modules\Kitchen\Services\KitchenService::CANCEL_REASONS as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <div class="flex gap-2">
                                        <button type="button" wire:click="confirmCancel" class="min-h-12 flex-1 bg-zinc-900 font-bold text-white dark:bg-zinc-100 dark:text-zinc-900">Batalkan pesanan</button>
                                        <button type="button" wire:click="$set('cancelling', null)" class="min-h-12 border-2 border-zinc-900 px-4 font-bold dark:border-zinc-100">Kembali</button>
                                    </div>
                                </div>
                            @else
                                <div class="flex gap-2 px-4 pb-4">
                                    @if ($tenantOrder->status === 'pending')
                                        <button type="button" wire:click="advance({{ $tenantOrder->id }}, 'preparing')" data-test="accept"
                                            @class(['min-h-14 flex-1 text-lg font-bold', 'bg-red-600 text-white' => ! $tenantOrder->scheduled_at, 'border-2 border-zinc-900 dark:border-zinc-100' => $tenantOrder->scheduled_at])>Terima →</button>
                                    @elseif (in_array($tenantOrder->status, ['accepted', 'preparing'], true))
                                        <button type="button" wire:click="advance({{ $tenantOrder->id }}, '{{ $tenantOrder->status === 'accepted' ? 'preparing' : 'ready' }}')" data-test="ready"
                                            class="min-h-14 flex-1 bg-red-600 text-lg font-bold text-white">{{ $tenantOrder->status === 'accepted' ? 'Mulai masak →' : 'Selesai ✓' }}</button>
                                    @elseif ($tenantOrder->status === 'ready')
                                        <button type="button" wire:click="advance({{ $tenantOrder->id }}, 'completed')" data-test="handover"
                                            class="min-h-14 flex-1 border-2 border-zinc-900 text-lg font-bold dark:border-zinc-100">Diserahkan ✓</button>
                                    @endif
                                    <button type="button" wire:click="startCancel({{ $tenantOrder->id }})" data-test="cancel"
                                        class="min-h-14 border-2 border-zinc-900 px-4 text-lg font-bold dark:border-zinc-100">Batalkan</button>
                                </div>
                            @endif
                        </article>
                    @empty
                        <p class="py-6 text-center text-sm text-zinc-400">—</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</div>
