<?php

use App\Models\CustomerSession;
use App\Modules\Ordering\Data\CartView;
use App\Modules\Ordering\Exceptions\CartException;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\ResolveCustomerSession;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Keranjang pelanggan. Sesi diambil dari cookie tepercaya (ResolveCustomerSession), BUKAN
 * dari prop klien. Semua harga di tampilan berasal dari CartService::view() yang selalu
 * merevalidasi harga/stok dari basis data. Tanpa sesi aktif, keranjang tidak bisa diisi.
 * UC-03: item dikelompokkan per tenant dengan subtotal, pajak, biaya layanan, dan total;
 * menu bermodifier diteruskan ke formulir kustomisasi UC-04 (ordering::item-customizer).
 * Tombol Checkout membuka halaman UC-05 (ordering::checkout); pesanan dibuat di sana.
 */
new class extends Component
{
    public string $canteenSlug = '';

    public function mount(string $canteenSlug): void
    {
        $this->canteenSlug = $canteenSlug;
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

    #[On('cart-add')]
    public function add(int $menuId): void
    {
        $session = $this->session();
        if ($session === null) {
            return;
        }

        // UC-04 extension point "pemilihan item bermodifier".
        if (app(CartService::class)->modifierGroupsFor($session, $menuId)->isNotEmpty()) {
            $this->dispatch('customize-item', menuId: $menuId);

            return;
        }

        try {
            app(CartService::class)->add($session, $menuId, 1);
            $this->resetErrorBag('cart');
            unset($this->cart);
        } catch (CartException $e) {
            $this->addError('cart', $e->getMessage());
        }
    }

    #[On('cart-updated')]
    public function refreshCart(): void
    {
        $this->resetErrorBag('cart');
        unset($this->cart);
    }

    #[Computed]
    public function tableLabel(): ?string
    {
        return $this->session()?->diningTable?->label;
    }

    public function increment(string $lineKey): void
    {
        $this->changeBy($lineKey, 1);
    }

    public function decrement(string $lineKey): void
    {
        $this->changeBy($lineKey, -1);
    }

    private function changeBy(string $lineKey, int $delta): void
    {
        $session = $this->session();
        if ($session === null) {
            return;
        }

        $view = app(CartService::class)->view($session);
        foreach ($view->lines as $line) {
            if ($line->lineKey === $lineKey) {
                app(CartService::class)->setQuantity($session, $lineKey, $line->quantity + $delta);
                break;
            }
        }

        unset($this->cart);
    }

    public function remove(string $lineKey): void
    {
        $session = $this->session();
        if ($session === null) {
            return;
        }

        app(CartService::class)->remove($session, $lineKey);
        unset($this->cart);
    }
};
?>

<div class="rounded-2xl border border-zinc-200 dark:border-zinc-800" aria-label="Keranjang">
    <div class="flex flex-wrap items-baseline justify-between gap-x-3 border-b-2 border-zinc-900 p-4 dark:border-zinc-100">
        <h2 class="text-xl font-extrabold">Keranjang</h2>
        <span class="whitespace-nowrap text-xs font-semibold text-zinc-500">
            @if ($this->tableLabel) {{ $this->tableLabel }} · @endif
            @if ($this->cart && $this->cart->totalQuantity > 0) {{ $this->cart->totalQuantity }} item @endif
        </span>
    </div>

    @if (session('checkout_error'))
        <div class="m-4 rounded-lg bg-red-100 px-3 py-2 text-sm font-medium text-red-800 dark:bg-red-900/40 dark:text-red-300" role="alert" data-test="checkout-error">{{ session('checkout_error') }}</div>
    @endif

    @error('cart')
        <div class="m-4 rounded-lg bg-red-100 px-3 py-2 text-sm font-medium text-red-800 dark:bg-red-900/40 dark:text-red-300" role="alert">{{ $message }}</div>
    @enderror

    @if (! $this->cart)
        <div class="p-4"><x-empty-state title="Belum ada sesi" description="Pindai QR di meja untuk mulai memesan." /></div>
    @elseif ($this->cart->isEmpty())
        <div class="p-4"><x-empty-state title="Keranjang kosong" description="Tambahkan menu dari katalog." /></div>
    @else
        @foreach ($this->cart->tenantGroups() as $group)
            <section wire:key="tenant-{{ $group['tenant_id'] }}" data-test="cart-tenant">
                <div class="flex items-baseline justify-between bg-zinc-100 px-4 py-2 dark:bg-zinc-800">
                    <h3 class="text-sm font-extrabold uppercase tracking-wide">{{ $group['tenant_name'] }}</h3>
                    <span class="text-xs font-semibold text-zinc-500">Subtotal Rp{{ number_format($group['subtotal'], 0, ',', '.') }}</span>
                </div>
                <ul class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach ($group['lines'] as $line)
                        <li wire:key="line-{{ $line->lineKey }}" @class(['flex items-center gap-3 px-4 py-3', 'opacity-70' => ! $line->available])>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold">{{ $line->name }}</p>
                                @if ($line->modifiers !== [] || $line->note)
                                    <p class="text-xs text-zinc-500">
                                        {{ collect($line->modifiers)->map(fn ($m) => '+ '.$m['name'])->implode(' · ') }}
                                        @if ($line->note) {{ $line->modifiers !== [] ? '·' : '' }} "{{ $line->note }}" @endif
                                    </p>
                                @endif
                                <p class="text-sm font-semibold">Rp{{ number_format($line->unitPrice + $line->modifierTotal, 0, ',', '.') }}{{ $line->quantity > 1 ? ' × '.$line->quantity : '' }}</p>
                                @if (! $line->available)
                                    <p class="mt-1 text-xs font-medium text-red-600">
                                        @if (in_array('menu_unavailable', $line->issues, true)) Menu telah habis. @endif
                                        @if (in_array('insufficient_stock', $line->issues, true)) Stok tidak mencukupi. @endif
                                        @if (in_array('modifier_unavailable', $line->issues, true)) Pilihan tambahan tidak tersedia. @endif
                                    </p>
                                @elseif ($line->priceChanged())
                                    <p class="mt-1 text-xs font-medium text-amber-600">Harga diperbarui menjadi Rp{{ number_format($line->unitPrice + $line->modifierTotal, 0, ',', '.') }}.</p>
                                @endif
                                <button type="button" wire:click="remove('{{ $line->lineKey }}')" class="text-xs text-red-600 underline">Hapus</button>
                            </div>
                            <div class="flex shrink-0 border-2 border-zinc-900 dark:border-zinc-100">
                                <button type="button" wire:click="decrement('{{ $line->lineKey }}')" class="min-h-10 min-w-10 font-bold" aria-label="Kurangi">−</button>
                                <span class="flex min-w-10 items-center justify-center border-x-2 border-zinc-900 font-bold dark:border-zinc-100">{{ $line->quantity }}</span>
                                <button type="button" wire:click="increment('{{ $line->lineKey }}')" class="min-h-10 min-w-10 font-bold" aria-label="Tambah">+</button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach

        <dl class="m-4 divide-y divide-zinc-200 border-2 border-zinc-900 text-sm dark:divide-zinc-700 dark:border-zinc-100" data-test="cart-summary">
            <div class="flex justify-between px-3 py-2"><dt>Subtotal ({{ count($this->cart->tenantGroups()) }} tenant)</dt><dd class="font-semibold">Rp{{ number_format($this->cart->subtotal, 0, ',', '.') }}</dd></div>
            <div class="flex justify-between px-3 py-2"><dt>Pajak</dt><dd class="font-semibold">Rp{{ number_format($this->cart->taxAmount, 0, ',', '.') }}</dd></div>
            <div class="flex justify-between px-3 py-2"><dt>Biaya layanan</dt><dd class="font-semibold">Rp{{ number_format($this->cart->serviceFeeAmount, 0, ',', '.') }}</dd></div>
            <div class="flex justify-between border-t-2 border-zinc-900 px-3 py-2 text-base font-extrabold dark:border-zinc-100"><dt>Total</dt><dd>Rp{{ number_format($this->cart->grandTotal(), 0, ',', '.') }}</dd></div>
        </dl>

        @if ($this->cart->hasBlockingIssues)
            <p class="mx-4 text-xs text-red-600">Perbaiki item bermasalah sebelum melanjutkan.</p>
        @endif
    @endif

    {{-- UC-03 alur 6a: keranjang kosong -> tombol checkout nonaktif. UC-05 langkah 1: buka checkout. --}}
    <div class="p-4">
        @if ($this->cart?->isOrderable())
            <a href="{{ route('customer.checkout', ['canteen' => $canteenSlug]) }}" wire:navigate data-test="checkout-button"
                class="flex min-h-12 w-full items-center justify-between bg-red-600 px-4 font-bold text-white">
                <span>Checkout</span>
                <span>Rp{{ number_format($this->cart->grandTotal(), 0, ',', '.') }}</span>
            </a>
        @else
            <button type="button" disabled data-test="checkout-button"
                class="flex min-h-12 w-full cursor-not-allowed items-center justify-between bg-zinc-300 px-4 font-bold text-zinc-600">
                <span>Checkout</span>
                <span>Rp{{ number_format($this->cart?->grandTotal() ?? 0, 0, ',', '.') }}</span>
            </button>
        @endif
    </div>
</div>
