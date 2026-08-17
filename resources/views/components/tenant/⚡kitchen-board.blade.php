<?php

use App\Models\Tenant;
use App\Models\TenantOrder;
use App\Models\UserTenantRole;
use App\Modules\Kitchen\Exceptions\KitchenException;
use App\Modules\Kitchen\Services\KitchenService;
use App\Support\Realtime\TenantChannels;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Kitchen Display System (KDS) tenant. tenantId prop publik TIDAK dipercaya: booted()
 * memverifikasi ulang membership DAN mengisi TenantContext tiap request (query ter-scope tenant).
 * Realtime via Echo private channel tenant.{id}.orders (Reverb); wire:poll sebagai fallback.
 */
new class extends Component
{
    public int $tenantId = 0;

    /** @var array<string, string> */
    private const COLUMNS = [
        'pending' => 'Baru',
        'accepted' => 'Diterima',
        'preparing' => 'Disiapkan',
        'ready' => 'Siap Diambil',
    ];

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
            $channel.',.NewTenantOrderReceived' => 'refresh',
        ];
    }

    public function refresh(): void
    {
        unset($this->orders);
    }

    /**
     * @return array<string, \Illuminate\Support\Collection<int, TenantOrder>>
     */
    #[Computed]
    public function orders(): array
    {
        $orders = TenantOrder::query()
            ->whereIn('status', array_keys(self::COLUMNS))
            ->whereHas('order', fn ($q) => $q->where('status', 'paid'))
            ->with(['order:id,order_number,table_snapshot', 'items:id,tenant_order_id,name_snapshot,quantity'])
            ->orderBy('created_at')
            ->get();

        $grouped = [];
        foreach (array_keys(self::COLUMNS) as $status) {
            $grouped[$status] = $orders->where('status', $status)->values();
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

    /**
     * @return array<string, string>
     */
    public function columns(): array
    {
        return self::COLUMNS;
    }
};
?>

<div class="space-y-4" wire:poll.10s>
    @error('kitchen')
        <div class="rounded-lg bg-red-100 px-3 py-2 text-sm text-red-800 dark:bg-red-900/40 dark:text-red-300">{{ $message }}</div>
    @enderror

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($this->columns() as $status => $label)
            <section class="rounded-2xl border border-zinc-200 p-3 dark:border-zinc-800">
                <h2 class="mb-2 flex items-center justify-between font-semibold">
                    <span>{{ $label }}</span>
                    <span class="rounded-full bg-zinc-200 px-2 text-xs dark:bg-zinc-700">{{ $this->orders[$status]->count() }}</span>
                </h2>

                <div class="space-y-2">
                    @forelse ($this->orders[$status] as $tenantOrder)
                        <article wire:key="to-{{ $tenantOrder->id }}" class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-800">
                            <div class="flex items-center justify-between">
                                <p class="font-medium">{{ $tenantOrder->order->order_number }}</p>
                                @if (($tenantOrder->order->table_snapshot['code'] ?? null))
                                    <span class="text-xs text-zinc-500">{{ $tenantOrder->order->table_snapshot['code'] }}</span>
                                @endif
                            </div>
                            <ul class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                @foreach ($tenantOrder->items as $item)
                                    <li>{{ $item->quantity }}× {{ $item->name_snapshot }}</li>
                                @endforeach
                            </ul>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach (\App\Modules\Kitchen\Services\KitchenService::nextStates($status) as $next)
                                    <button type="button" wire:click="advance({{ $tenantOrder->id }}, '{{ $next }}')"
                                        class="min-h-9 rounded-lg border border-zinc-300 px-3 text-sm font-medium dark:border-zinc-600">
                                        {{ ['accepted' => 'Terima', 'preparing' => 'Siapkan', 'ready' => 'Siap', 'completed' => 'Selesai', 'cancelled' => 'Batal'][$next] ?? $next }}
                                    </button>
                                @endforeach
                            </div>
                        </article>
                    @empty
                        <p class="py-6 text-center text-sm text-zinc-400">—</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</div>
