<?php

use App\Models\CustomerSession;
use App\Models\Tenant;
use App\Modules\Ordering\Data\CartView;
use App\Modules\Ordering\Data\CheckoutDetails;
use App\Modules\Ordering\Exceptions\CheckoutException;
use App\Modules\Ordering\Exceptions\PreOrderException;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\CheckoutService;
use App\Modules\Ordering\Services\PreOrderScheduler;
use App\Modules\Ordering\Services\ResolveCustomerSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * UC-05 Checkout Pesanan (+ extend UC-06 Jadwalkan Pre-Order pada pemilihan mode penyajian).
 * Ringkasan dihitung ulang dari CartService (bukan dari klien); CheckoutService memvalidasi ulang
 * stok/harga dan jadwal di dalam satu transaksi. Item bermasalah → kembali ke keranjang (alur 4a).
 */
new class extends Component
{
    public string $canteenSlug = '';

    public string $serviceMode = CheckoutDetails::DINE_IN;

    public string $customerName = '';

    public string $whatsapp = '';

    public string $pickupDate = '';

    /** Waktu slot ISO-8601 (mis. 2026-07-18T12:00:00+07:00); divalidasi ulang di server. */
    public string $pickupSlot = '';

    /** Daftar slot diringkas (12 pertama) sampai pelanggan meminta semua. */
    public bool $showAllSlots = false;

    /** @var list<string> slot terdekat yang ditawarkan saat jadwal ditolak (alur 2a) */
    public array $alternatives = [];

    /** Idempotency stabil per muat halaman: klik ganda tak membuat order dobel. */
    public string $idempotencyKey = '';

    public function mount(string $canteenSlug): void
    {
        $this->canteenSlug = $canteenSlug;
        $this->idempotencyKey = (string) Str::uuid();

        if ($this->cart === null || ! $this->cart->isOrderable()) {
            $this->redirectRoute('customer.home', ['canteen' => $canteenSlug], navigate: true);
        }
    }

    private function session(): ?CustomerSession
    {
        return app(ResolveCustomerSession::class)->current(request());
    }

    #[Computed]
    public function cart(): ?CartView
    {
        $session = $this->session();

        return $session ? app(CartService::class)->view($session) : null;
    }

    #[Computed]
    public function tableLabel(): ?string
    {
        return $this->session()?->diningTable?->label;
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Tenant> */
    #[Computed]
    public function tenants(): \Illuminate\Database\Eloquent\Collection
    {
        $ids = collect($this->cart?->lines ?? [])->pluck('tenantId')->unique()->values()->all();

        return Tenant::query()->whereIn('id', $ids)
            ->with(['operatingHours' => fn ($query) => $query->withoutGlobalScope('tenant')])
            ->orderBy('display_name')->get();
    }

    #[Computed]
    public function preOrderAvailable(): bool
    {
        return $this->tenants->isNotEmpty() && $this->tenants->every(fn (Tenant $tenant): bool => $tenant->pre_order_enabled);
    }

    /** @return list<array{date: string, label: string}> */
    #[Computed]
    public function days(): array
    {
        return $this->preOrderAvailable ? app(PreOrderScheduler::class)->days($this->tenants) : [];
    }

    /** @return list<array{time: CarbonImmutable, label: string, state: string}> */
    #[Computed]
    public function pickupSlots(): array
    {
        if ($this->pickupDate === '' || ! $this->preOrderAvailable) {
            return [];
        }

        $slots = app(PreOrderScheduler::class)->slots($this->tenants, CarbonImmutable::parse($this->pickupDate, (string) config('app.display_timezone')));

        // Tampilkan hanya dua slot lampau terakhir (dicoret) agar daftar tetap ringkas.
        $past = array_keys(array_filter($slots, fn (array $slot): bool => $slot['state'] === 'past'));

        $slots = array_values(array_filter($slots, fn (array $slot, int $index): bool => $slot['state'] !== 'past' || $index >= (end($past) ?: 0) - 1, ARRAY_FILTER_USE_BOTH));

        return $this->showAllSlots ? $slots : array_slice($slots, 0, 12);
    }

    /** Estimasi mulai dimasak (waktu ambil − penyiapan terlama di keranjang). */
    #[Computed]
    public function releaseLabel(): ?string
    {
        $slot = $this->selectedSlot();
        if ($slot === null) {
            return null;
        }

        $prep = (int) collect($this->cart?->lines ?? [])->max('prepMinutes');

        return app(PreOrderScheduler::class)->releaseAt($slot, $prep)->setTimezone((string) config('app.display_timezone'))->format('H.i');
    }

    public function chooseMode(string $mode): void
    {
        if ($mode === CheckoutDetails::PICKUP && ! $this->preOrderAvailable) {
            return;
        }

        $this->serviceMode = $mode === CheckoutDetails::PICKUP ? CheckoutDetails::PICKUP : CheckoutDetails::DINE_IN;
        if ($this->serviceMode === CheckoutDetails::PICKUP && $this->pickupDate === '') {
            $this->pickupDate = $this->days[0]['date'] ?? '';
        }
    }

    public function chooseDay(string $date): void
    {
        $this->pickupDate = $date;
        $this->pickupSlot = '';
        $this->alternatives = [];
        $this->showAllSlots = false;
    }

    public function showAll(): void
    {
        $this->showAllSlots = true;
    }

    public function chooseSlot(string $iso): void
    {
        $this->pickupSlot = $iso;
        $this->alternatives = [];
        $this->resetErrorBag('pickupSlot');
    }

    private function selectedSlot(): ?CarbonImmutable
    {
        if ($this->pickupSlot === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($this->pickupSlot);
        } catch (\Throwable) {
            return null;
        }
    }

    public function confirm()
    {
        $session = $this->session();
        if ($session === null) {
            return $this->redirectRoute('customer.home', ['canteen' => $this->canteenSlug], navigate: true);
        }

        $this->validate([
            'serviceMode' => ['required', 'in:dine_in,pickup'],
            'customerName' => ['required', 'string', 'max:60'],
            'whatsapp' => ['required', 'string', 'regex:/^(\+?62|0)8[\d\s\-]{7,14}$/'],
            'pickupSlot' => ['required_if:serviceMode,pickup', 'nullable', 'date'],
        ], [
            'whatsapp.regex' => 'Nomor WhatsApp tidak valid (contoh: 0812-3456-7890).',
            'pickupSlot.required_if' => 'Pilih slot waktu ambil.',
        ], ['customerName' => 'nama', 'whatsapp' => 'nomor WhatsApp', 'pickupSlot' => 'waktu ambil']);

        $details = new CheckoutDetails($this->serviceMode, trim(strip_tags($this->customerName)), CheckoutDetails::normalizeWhatsapp($this->whatsapp));
        $scheduledAt = $details->isPickup() ? $this->selectedSlot() : null;

        try {
            $result = app(CheckoutService::class)->checkout($session, $this->idempotencyKey, $scheduledAt, $details);
        } catch (PreOrderException $e) {
            $this->alternatives = array_map(fn (CarbonImmutable $slot): string => $slot->toIso8601String(), $e->alternatives);
            $this->addError('pickupSlot', $e->getMessage());
            unset($this->pickupSlots);

            return null;
        } catch (CheckoutException $e) {
            // UC-05 alur 4a: batalkan checkout, kembali ke keranjang yang menandai item bermasalah.
            session()->flash('checkout_error', $e->getMessage());

            return $this->redirectRoute('customer.home', ['canteen' => $this->canteenSlug], navigate: false);
        }

        if ($result->trackingToken !== null) {
            Cookie::queue(cookie(
                name: 'order_tracking',
                value: $result->trackingToken,
                minutes: 240,
                path: '/',
                domain: null,
                secure: request()->isSecure() || app()->isProduction(),
                httpOnly: true,
                raw: false,
                sameSite: 'lax',
            ));
        }

        return $this->redirectRoute('customer.order.show', ['canteen' => $this->canteenSlug], navigate: false);
    }
};
?>

@php($rupiah = fn (int $n) => 'Rp'.number_format($n, 0, ',', '.'))
@php($tz = (string) config('app.display_timezone'))
<div aria-label="Checkout" class="-m-4">
    <div class="flex items-baseline justify-between border-b-2 border-zinc-900 p-4 dark:border-zinc-100">
        <h2 class="text-2xl font-extrabold">{{ $serviceMode === 'pickup' ? 'Pesan dulu / Pick-up' : 'Checkout' }}</h2>
        @if ($serviceMode === 'pickup')
            <span class="text-sm font-extrabold uppercase text-red-600">Pre-order</span>
        @elseif ($this->cart)
            <span class="text-sm font-semibold text-zinc-500">{{ $this->cart->totalQuantity }} item · {{ count($this->cart->tenantGroups()) }} tenant</span>
        @endif
    </div>

    @if ($this->cart && ! $this->cart->isEmpty())
        <div class="space-y-5 p-4">
            <section>
                <h3 class="mb-2 text-sm font-extrabold uppercase tracking-widest">Mode penyajian</h3>
                <div class="grid grid-cols-2 border-2 border-zinc-900 dark:border-zinc-100" role="radiogroup">
                    <button type="button" wire:click="chooseMode('dine_in')" role="radio" aria-checked="{{ $serviceMode === 'dine_in' ? 'true' : 'false' }}" data-test="mode-dine-in"
                        @class(['p-3 text-left', 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => $serviceMode === 'dine_in'])>
                        <span class="block font-bold">Makan di tempat</span>
                        <span class="block text-xs opacity-80">{{ $this->tableLabel ? 'Diantar ke '.$this->tableLabel : 'Ambil di konter' }}</span>
                    </button>
                    <button type="button" wire:click="chooseMode('pickup')" role="radio" aria-checked="{{ $serviceMode === 'pickup' ? 'true' : 'false' }}" data-test="mode-pickup"
                        @disabled(! $this->preOrderAvailable)
                        @class(['p-3 text-left disabled:cursor-not-allowed disabled:opacity-50', 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => $serviceMode === 'pickup'])>
                        <span class="block font-bold">Pesan dulu / Pick-up</span>
                        <span class="block text-xs opacity-80">{{ $this->preOrderAvailable ? 'Jadwalkan waktu ambil' : 'Belum didukung semua tenant' }}</span>
                    </button>
                </div>
            </section>

            @if ($serviceMode === 'pickup')
                <section data-test="pre-order">
                    <h3 class="mb-2 text-sm font-extrabold uppercase tracking-widest">Hari pengambilan</h3>
                    <div class="flex flex-wrap gap-2">
                        @forelse ($this->days as $day)
                            <button type="button" wire:key="day-{{ $day['date'] }}" wire:click="chooseDay('{{ $day['date'] }}')"
                                @class(['border-2 border-zinc-900 px-3 py-2 text-sm font-bold dark:border-zinc-100', 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => $pickupDate === $day['date']])>{{ $day['label'] }}</button>
                        @empty
                            <p class="text-sm text-zinc-500">Belum ada slot pengambilan yang tersedia.</p>
                        @endforelse
                    </div>

                    <div class="mb-2 mt-4 flex items-baseline justify-between">
                        <h3 class="text-sm font-extrabold uppercase tracking-widest">Slot waktu ambil</h3>
                        <span class="text-xs text-zinc-500">min. 15 mnt dari sekarang</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2" data-test="slots">
                        @foreach ($this->pickupSlots as $slot)
                            @php($iso = $slot['time']->toIso8601String())
                            <button type="button" wire:key="slot-{{ $iso }}" wire:click="chooseSlot('{{ $iso }}')" @disabled($slot['state'] !== 'available')
                                @class([
                                    'min-h-11 border-2 text-sm font-bold',
                                    'border-zinc-300 text-zinc-400 line-through' => $slot['state'] === 'past',
                                    'border-zinc-300 text-zinc-400' => $slot['state'] === 'full',
                                    'border-red-600 bg-red-600 text-white' => $slot['state'] === 'available' && $pickupSlot === $iso,
                                    'border-zinc-900 dark:border-zinc-100' => $slot['state'] === 'available' && $pickupSlot !== $iso,
                                ])>
                                {{ $slot['label'] }}
                                @if ($slot['state'] === 'full')<span class="block text-[10px] tracking-widest no-underline">PENUH</span>@endif
                            </button>
                        @endforeach
                    </div>
                    @if (! $showAllSlots && count($this->pickupSlots) === 12)
                        <button type="button" wire:click="showAll" class="mt-2 text-sm underline">Tampilkan semua slot</button>
                    @endif

                    @if ($this->releaseLabel)
                        <div class="mt-4 border-2 border-zinc-900 p-3 text-sm dark:border-zinc-100" data-test="release-info">
                            <div class="flex justify-between"><span>Waktu ambil dipilih</span><span class="font-bold">{{ \Carbon\CarbonImmutable::parse($pickupSlot)->setTimezone($tz)->format('H.i') }} WIB</span></div>
                            <div class="flex justify-between"><span>Mulai dimasak otomatis</span><span class="font-bold">± {{ $this->releaseLabel }}</span></div>
                            <p class="mt-1 text-xs text-zinc-500">Pesanan ditahan berstatus "terjadwal" setelah dibayar, lalu dilepas ke antrean dapur agar siap tepat waktu (toleransi ± 1 mnt).</p>
                        </div>
                    @endif

                    @error('pickupSlot')
                        <div class="mt-3 border-l-4 border-red-600 bg-white p-3 text-sm dark:bg-zinc-900" role="alert" data-test="slot-error">
                            <span class="font-bold">{{ $message }}</span>
                            @if ($alternatives !== [])
                                — slot terdekat yang tersedia:
                                @foreach ($alternatives as $alternative)
                                    <button type="button" wire:click="chooseSlot('{{ $alternative }}')" class="font-bold underline">{{ \Carbon\CarbonImmutable::parse($alternative)->setTimezone($tz)->format('H.i') }}</button>@if (! $loop->last) / @endif
                                @endforeach
                            @endif
                        </div>
                    @enderror
                </section>
            @endif

            <section>
                <h3 class="mb-2 text-sm font-extrabold uppercase tracking-widest">Identitas ringkas</h3>
                <label for="customerName" class="text-sm font-semibold text-zinc-500">Nama</label>
                <input id="customerName" type="text" wire:model="customerName" maxlength="60" autocomplete="name"
                    class="mt-1 block w-full border-2 border-zinc-900 bg-white px-3 py-2 font-semibold dark:border-zinc-100 dark:bg-zinc-900" />
                @error('customerName')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror

                <label for="whatsapp" class="mt-3 block text-sm font-semibold text-zinc-500">Nomor WhatsApp <span class="font-normal">(untuk notifikasi status)</span></label>
                <input id="whatsapp" type="tel" wire:model="whatsapp" inputmode="tel" autocomplete="tel" placeholder="0812-3456-7890"
                    class="mt-1 block w-full border-2 border-zinc-300 bg-white px-3 py-2 font-semibold dark:border-zinc-600 dark:bg-zinc-900" />
                @error('whatsapp')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </section>

            <section>
                <h3 class="mb-2 text-sm font-extrabold uppercase tracking-widest">Ringkasan</h3>
                <dl class="divide-y divide-zinc-200 border-2 border-zinc-900 dark:divide-zinc-700 dark:border-zinc-100" data-test="checkout-summary">
                    @foreach ($this->cart->tenantGroups() as $group)
                        <div class="flex justify-between px-3 py-2"><dt>{{ $group['tenant_name'] }} · {{ collect($group['lines'])->sum('quantity') }} item</dt><dd class="font-semibold">{{ $rupiah($group['subtotal']) }}</dd></div>
                    @endforeach
                    <div class="flex justify-between px-3 py-2"><dt>Pajak + layanan</dt><dd class="font-semibold">{{ $rupiah($this->cart->taxAmount + $this->cart->serviceFeeAmount) }}</dd></div>
                    <div class="flex justify-between border-t-2 border-zinc-900 px-3 py-2 text-lg font-extrabold dark:border-zinc-100"><dt>Total</dt><dd>{{ $rupiah($this->cart->grandTotal()) }}</dd></div>
                </dl>
                <p class="mt-2 text-xs text-zinc-500">Sistem memvalidasi ulang stok &amp; harga saat konfirmasi. Pesanan dibuat atomik — tidak ada pesanan parsial.</p>
            </section>
        </div>

        <div class="border-t-2 border-zinc-900 p-4 dark:border-zinc-100">
            <button type="button" wire:click="confirm" wire:loading.attr="disabled" data-test="confirm-checkout"
                class="min-h-12 w-full bg-red-600 font-bold text-white disabled:opacity-60">
                {{ $serviceMode === 'pickup' ? 'Konfirmasi jadwal & lanjut bayar →' : 'Buat pesanan & bayar via QRIS →' }}
            </button>
            <a href="{{ route('customer.home', ['canteen' => $canteenSlug]) }}#keranjang" class="mt-3 block text-center text-sm underline">← Kembali ke keranjang</a>
        </div>
    @endif
</div>
