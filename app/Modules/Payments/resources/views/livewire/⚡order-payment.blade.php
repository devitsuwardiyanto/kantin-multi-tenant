<?php

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Modules\Ordering\Exceptions\CheckoutException;
use App\Modules\Ordering\Services\ResolveTrackedOrder;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Exceptions\PaymentException;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Payments\Services\QrisImage;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * UC-07 Bayar via QRIS Dinamis (di-include UC-05). Order diambil dari cookie pelacakan
 * tepercaya (ResolveTrackedOrder), bukan prop klien. QRIS dibangkitkan otomatis saat halaman
 * dibuka, ditampilkan sebagai gambar dengan penghitung mundur, dan status dipantau (poll 3 dtk)
 * sampai verifikasi pembayaran (UC-08). Tombol simulasi hanya tampil pada gateway sandbox.
 */
new class extends Component
{
    public string $canteenSlug = '';

    /** Pesan gangguan gateway (alur 1a); disimpan sebagai state agar tidak hilang saat poll. */
    public string $paymentError = '';

    /** Status saat halaman dibuka: bila berubah (dibayar/dibatalkan), halaman dimuat ulang sekali. */
    public string $initialStatus = '';

    public function mount(string $canteenSlug): void
    {
        $this->canteenSlug = $canteenSlug;
        $this->initialStatus = (string) $this->order?->status;

        // UC-05 langkah 6 → UC-07 langkah 1: tagihan dibuat tanpa klik tambahan.
        $order = $this->order;
        if ($order !== null && $order->isAwaitingPayment() && $this->activeAttempt() === null && ! $this->expired()) {
            $this->initiate();
        }
    }

    #[Computed]
    public function order(): ?Order
    {
        $order = app(ResolveTrackedOrder::class)->current(request());

        return $order?->load(['payment.latestAttempt', 'canteen']);
    }

    #[Computed]
    public function sandbox(): bool
    {
        return app(PaymentGateway::class)->isSandbox();
    }

    private function attempt(): ?PaymentAttempt
    {
        return $this->order?->payment?->latestAttempt;
    }

    private function activeAttempt(): ?PaymentAttempt
    {
        $attempt = $this->attempt();

        return $attempt !== null && $attempt->status === 'pending' && ! $attempt->isExpired() ? $attempt : null;
    }

    /** UC-07 alur 4a: QRIS terakhir kedaluwarsa sebelum dibayar. */
    public function expired(): bool
    {
        $attempt = $this->attempt();

        return $attempt !== null && ($attempt->status === 'expired' || ($attempt->status === 'pending' && $attempt->isExpired()));
    }

    #[Computed]
    public function qrSvg(): ?string
    {
        $payload = $this->activeAttempt()?->qris_payload;

        return $payload ? app(QrisImage::class)->svg($payload) : null;
    }

    /** NMID merchant tersamar, mis. "•••8821". */
    #[Computed]
    public function maskedNmid(): string
    {
        return '•••'.substr((string) config('services.qris.merchant_nmid'), -4);
    }

    public function initiate(): void
    {
        $order = app(ResolveTrackedOrder::class)->current(request());
        if ($order === null) {
            return;
        }

        try {
            app(PaymentService::class)->initiate($order);
            $this->paymentError = '';
        } catch (PaymentException $e) {
            $this->paymentError = $e->getMessage();
        }

        unset($this->order, $this->qrSvg);
    }

    /** UC-07 alur 4a: bangkitkan QRIS baru setelah kedaluwarsa. */
    public function regenerate(): void
    {
        $this->initiate();
    }

    public function cancel(): void
    {
        $order = app(ResolveTrackedOrder::class)->current(request());
        if ($order === null) {
            return;
        }

        try {
            app(PaymentService::class)->cancelUnpaid($order);
        } catch (CheckoutException $e) {
            $this->paymentError = $e->getMessage();
        }

        unset($this->order, $this->qrSvg);
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
            $this->paymentError = $e->getMessage();
        }

        unset($this->order, $this->qrSvg);
    }
};
?>

@php($order = $this->order)
<section class="border-2 border-zinc-900 dark:border-zinc-100" aria-label="Pembayaran" data-test="payment"
    @if ($order?->isAwaitingPayment()) wire:poll.3s @endif>
    @if (! $order)
        <div class="p-4"><x-empty-state title="Pesanan tidak ditemukan" /></div>
    @else
        @php($payment = $order->payment)
        @php($attempt = $payment?->latestAttempt)
        <div class="flex items-baseline justify-between border-b-2 border-zinc-900 p-4 dark:border-zinc-100">
            <h2 class="text-xl font-extrabold">Pembayaran QRIS</h2>
            <span class="text-sm font-semibold text-zinc-500">#{{ $order->order_number }}</span>
        </div>

        <div class="space-y-4 p-4">
            @if ($paymentError !== '')
                <div class="border-l-4 border-red-600 bg-white p-3 text-sm font-semibold text-red-700 dark:bg-zinc-900 dark:text-red-300" role="alert" data-test="payment-error">{{ $paymentError }}</div>
            @endif
            @if ($initialStatus === 'awaiting_payment' && $order->status !== 'awaiting_payment')
                {{-- Ringkasan pesanan di luar komponen ikut diperbarui (sekali, karena status awal berubah). --}}
                <span x-init="setTimeout(() => window.location.reload(), 1500)"></span>
            @endif

            @if ($order->status === 'paid')
                <div class="bg-green-100 px-4 py-3 text-sm font-semibold text-green-800 dark:bg-green-900/40 dark:text-green-300" role="status">
                    Pembayaran berhasil. Terima kasih!
                </div>
            @elseif ($order->status === 'cancelled')
                <div class="bg-zinc-100 px-4 py-3 text-sm font-semibold dark:bg-zinc-800" role="status" data-test="payment-cancelled">
                    Pesanan dibatalkan. Stok menu telah dikembalikan.
                </div>
            @else
                <div class="text-center">
                    <p class="text-sm text-zinc-500">Total pembayaran</p>
                    <p class="text-4xl font-extrabold" data-test="payment-amount">Rp{{ number_format($payment->amount ?? $order->grand_total_amount, 0, ',', '.') }}</p>
                </div>

                @if ($this->qrSvg && $attempt)
                    @php($total = max(1, (int) config('services.qris.expiry_seconds', 900)))
                    <div class="border-2 border-zinc-900 p-4 dark:border-zinc-100"
                        x-data="{ left: {{ max(0, (int) now()->diffInSeconds($attempt->expires_at, false)) }}, total: {{ $total }},
                                  tick() { if (this.left > 0) { this.left--; if (this.left === 0) { $wire.$refresh() } } },
                                  get label() { return String(Math.floor(this.left / 60)).padStart(2, '0') + ':' + String(this.left % 60).padStart(2, '0') } }"
                        x-init="setInterval(() => tick(), 1000)">
                        <div class="flex items-baseline justify-between">
                            <span class="font-extrabold tracking-widest">QRIS</span>
                            <span class="text-xs font-semibold uppercase text-zinc-500">{{ $order->canteen?->name }} · NMID {{ $this->maskedNmid }}</span>
                        </div>
                        <div class="mx-auto my-3 w-fit bg-white p-2 [&>svg]:size-56" data-test="qris-image" role="img" aria-label="Kode QRIS nominal Rp{{ number_format($payment->amount, 0, ',', '.') }}">{!! $this->qrSvg !!}</div>
                        <div class="flex items-baseline justify-between border-t border-zinc-300 pt-2 text-sm dark:border-zinc-700">
                            <span>Berlaku hingga</span>
                            <span class="text-xl font-extrabold text-red-600" x-text="label" data-test="countdown">{{ $attempt->expires_at->setTimezone(config('app.display_timezone'))->format('H.i') }}</span>
                        </div>
                        <div class="mt-2 h-1.5 bg-zinc-200 dark:bg-zinc-700"><div class="h-1.5 bg-red-600" :style="`width: ${Math.min(100, left / total * 100)}%`"></div></div>
                        <details class="mt-2 text-xs text-zinc-500">
                            <summary class="cursor-pointer">Referensi {{ $payment->payment_reference }}</summary>
                            <p class="mt-1 break-all font-mono">{{ $attempt->qris_payload }}</p>
                        </details>
                    </div>

                    <p class="flex items-center justify-center gap-2 font-semibold" role="status">
                        <span class="size-2.5 animate-pulse rounded-full bg-red-600"></span> Menunggu pembayaran…
                    </p>
                    <ol class="list-decimal space-y-1 ps-5 text-sm text-zinc-600 dark:text-zinc-400">
                        <li>Buka aplikasi pembayaran (GoPay, OVO, BCA, dll.)</li>
                        <li>Pindai kode QRIS di atas — nominal sudah terkunci.</li>
                        <li>Status terverifikasi otomatis, tanpa unggah bukti.</li>
                    </ol>

                    @if ($this->sandbox)
                        <button type="button" wire:click="simulatePay" wire:loading.attr="disabled" data-test="simulate-pay"
                            class="min-h-11 w-full border-2 border-dashed border-zinc-400 text-sm font-semibold disabled:opacity-50">
                            Simulasi Bayar (sandbox)
                        </button>
                    @endif
                @elseif ($this->expired())
                    <div class="border-2 border-zinc-900 p-4 text-center dark:border-zinc-100" data-test="qris-expired">
                        <p class="font-bold">QRIS kedaluwarsa sebelum dibayar.</p>
                        <p class="mt-1 text-sm text-zinc-500">Transaksi QRIS dibatalkan; pesanan tetap menunggu pembayaran.</p>
                        <button type="button" wire:click="regenerate" wire:loading.attr="disabled" data-test="regenerate"
                            class="mt-3 min-h-11 w-full bg-red-600 font-bold text-white disabled:opacity-60">Buat QRIS baru</button>
                    </div>
                @else
                    <button type="button" wire:click="initiate" wire:loading.attr="disabled" data-test="retry-payment"
                        class="min-h-11 w-full bg-red-600 font-bold text-white disabled:opacity-60">
                        {{ $payment ? 'Coba lagi' : 'Bayar dengan QRIS' }}
                    </button>
                @endif

                <button type="button" wire:click="cancel" wire:confirm="Batalkan pesanan ini?" data-test="cancel-order"
                    class="min-h-11 w-full border-2 border-zinc-900 font-bold dark:border-zinc-100">Batalkan pesanan</button>
            @endif
        </div>
    @endif
</section>
