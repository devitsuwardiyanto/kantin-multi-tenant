<?php

use App\Models\Order;
use App\Modules\Ordering\Services\ResolveTrackedOrder;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Exceptions\PaymentException;
use App\Modules\Payments\Services\PaymentService;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Kotak pembayaran QRIS pada halaman status pesanan. Order diambil dari cookie pelacakan
 * tepercaya (ResolveTrackedOrder), bukan prop klien. Tombol simulasi hanya tampil pada gateway
 * sandbox. QR raster ditunda (butuh lib QR) — payload EMVCo ditampilkan sebagai teks.
 */
new class extends Component
{
    public string $canteenSlug = '';

    public function mount(string $canteenSlug): void
    {
        $this->canteenSlug = $canteenSlug;
    }

    #[Computed]
    public function order(): ?Order
    {
        $order = app(ResolveTrackedOrder::class)->current(request());

        return $order?->load('payment.latestAttempt');
    }

    #[Computed]
    public function sandbox(): bool
    {
        return app(PaymentGateway::class)->isSandbox();
    }

    public function initiate(): void
    {
        $order = app(ResolveTrackedOrder::class)->current(request());
        if ($order === null) {
            return;
        }

        try {
            app(PaymentService::class)->initiate($order);
        } catch (PaymentException $e) {
            $this->addError('payment', $e->getMessage());
        }

        unset($this->order);
    }

    public function simulatePay(): void
    {
        $order = app(ResolveTrackedOrder::class)->current(request());
        $payment = $order?->payment;
        if ($payment === null) {
            return;
        }

        try {
            app(PaymentService::class)->confirmSandbox($payment);
        } catch (PaymentException $e) {
            $this->addError('payment', $e->getMessage());
        }

        unset($this->order);
    }
};
?>

<div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800" aria-label="Pembayaran">
    @php($order = $this->order)
    @if (! $order)
        <x-empty-state title="Pesanan tidak ditemukan" />
    @else
        @php($payment = $order->payment)

        <h2 class="mb-3 font-semibold">Pembayaran</h2>

        @error('payment')
            <div class="mb-3 rounded-lg bg-red-100 px-3 py-2 text-sm text-red-800 dark:bg-red-900/40 dark:text-red-300">{{ $message }}</div>
        @enderror

        @if ($order->status === 'paid')
            <div class="rounded-xl bg-green-100 px-4 py-3 text-sm font-medium text-green-800 dark:bg-green-900/40 dark:text-green-300">
                Pembayaran berhasil. Terima kasih!
            </div>
        @elseif ($payment === null)
            <p class="text-sm text-zinc-500">Total tagihan: <span class="font-semibold">Rp {{ number_format($order->grand_total_amount, 0, ',', '.') }}</span></p>
            <button type="button" wire:click="initiate" wire:loading.attr="disabled"
                class="mt-3 min-h-11 w-full rounded-xl bg-zinc-900 font-medium text-white disabled:opacity-50 dark:bg-white dark:text-zinc-900">
                Bayar dengan QRIS
            </button>
        @else
            @php($attempt = $payment->latestAttempt)
            <p class="text-sm text-zinc-500">Pindai QRIS berikut (nominal Rp {{ number_format($payment->amount, 0, ',', '.') }}):</p>
            <div class="mt-2 rounded-xl border border-dashed border-zinc-300 p-3 dark:border-zinc-700">
                <p class="break-all font-mono text-[11px] leading-relaxed text-zinc-600 dark:text-zinc-300">{{ $attempt?->qris_payload }}</p>
            </div>
            @if ($attempt?->expires_at)
                <p class="mt-1 text-xs text-zinc-500">Berlaku hingga {{ $attempt->expires_at->format('H:i') }}.</p>
            @endif

            @if ($this->sandbox)
                <button type="button" wire:click="simulatePay" wire:loading.attr="disabled"
                    class="mt-3 min-h-11 w-full rounded-xl border border-zinc-300 font-medium disabled:opacity-50 dark:border-zinc-600">
                    Simulasi Bayar (sandbox)
                </button>
            @endif
        @endif
    @endif
</div>
