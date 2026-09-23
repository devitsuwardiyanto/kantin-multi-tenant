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

        try {
            app(CartService::class)->add($session, $menuId, 1);
            unset($this->cart);
        } catch (CartException $e) {
            $this->addError('cart', $e->getMessage());
        }
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

<div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800" aria-label="Keranjang">
    <h2 class="mb-3 flex items-center justify-between font-semibold">
        <span>Keranjang</span>
        @if ($this->cart && $this->cart->totalQuantity > 0)
            <span class="rounded-full bg-zinc-900 px-2 py-0.5 text-xs text-white dark:bg-white dark:text-zinc-900">{{ $this->cart->totalQuantity }}</span>
        @endif
    </h2>

    @error('cart')
        <div class="mb-3 rounded-lg bg-red-100 px-3 py-2 text-sm text-red-800 dark:bg-red-900/40 dark:text-red-300">{{ $message }}</div>
    @enderror

    @if (! $this->cart)
        <x-empty-state title="Belum ada sesi" description="Pindai QR di meja untuk mulai memesan." />
    @elseif ($this->cart->isEmpty())
        <x-empty-state title="Keranjang kosong" description="Tambahkan menu dari katalog." />
    @else
        <ul class="space-y-3">
            @foreach ($this->cart->lines as $line)
                <li wire:key="line-{{ $line->lineKey }}" class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-800 {{ $line->available ? '' : 'opacity-70' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ $line->name }}</p>
                            <p class="truncate text-xs text-zinc-500">{{ $line->tenantName }}</p>
                            @foreach ($line->modifiers as $modifier)
                                <p class="truncate text-xs text-zinc-500">+ {{ $modifier['name'] }}</p>
                            @endforeach
                        </div>
                        <p class="shrink-0 font-semibold">Rp {{ number_format($line->lineTotal, 0, ',', '.') }}</p>
                    </div>

                    @if (! $line->available)
                        <p class="mt-1 text-xs font-medium text-red-600">
                            @if (in_array('menu_unavailable', $line->issues, true)) Menu tidak lagi tersedia. @endif
                            @if (in_array('insufficient_stock', $line->issues, true)) Stok tidak mencukupi. @endif
                            @if (in_array('modifier_unavailable', $line->issues, true)) Pilihan tambahan tidak tersedia. @endif
                        </p>
                    @elseif ($line->priceChanged())
                        <p class="mt-1 text-xs font-medium text-amber-600">Harga diperbarui menjadi Rp {{ number_format($line->unitPrice + $line->modifierTotal, 0, ',', '.') }}.</p>
                    @endif

                    <div class="mt-2 flex items-center gap-2">
                        <button type="button" wire:click="decrement('{{ $line->lineKey }}')" class="min-h-9 min-w-9 rounded-lg border border-zinc-300 dark:border-zinc-600" aria-label="Kurangi">−</button>
                        <span class="w-8 text-center text-sm font-medium">{{ $line->quantity }}</span>
                        <button type="button" wire:click="increment('{{ $line->lineKey }}')" class="min-h-9 min-w-9 rounded-lg border border-zinc-300 dark:border-zinc-600" aria-label="Tambah">+</button>
                        <button type="button" wire:click="remove('{{ $line->lineKey }}')" class="ms-auto text-sm text-red-600 underline">Hapus</button>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-4 flex items-center justify-between border-t border-zinc-200 pt-3 dark:border-zinc-800">
            <span class="text-sm text-zinc-500">Subtotal</span>
            <span class="text-lg font-bold">Rp {{ number_format($this->cart->subtotal, 0, ',', '.') }}</span>
        </div>

        @if ($this->cart->hasBlockingIssues)
            <p class="mt-2 text-xs text-red-600">Perbaiki item bermasalah sebelum melanjutkan.</p>
        @endif

        <button type="button" @disabled(! $this->cart->isOrderable())
            class="mt-3 min-h-11 w-full rounded-xl bg-zinc-900 font-medium text-white disabled:opacity-50 dark:bg-white dark:text-zinc-900">
            Lanjut ke Pembayaran
        </button>
    @endif
</div>
