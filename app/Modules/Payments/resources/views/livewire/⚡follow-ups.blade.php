<?php

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\TenantOrder;
use App\Models\UserCanteenRole;
use App\Modules\Kitchen\Services\KitchenService;
use App\Modules\Payments\Exceptions\FollowUpException;
use App\Modules\Payments\Services\FollowUpService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * “Perlu Tindak Lanjut” pengelola kantin (temuan audit Pertemuan 14):
 *  - UC-08 alur 3a: pembayaran perlu ditinjau → Terima pembayaran / Dana dikembalikan.
 *  - UC-15 alur 4a: sub-pesanan dibatalkan dapur → Tandai dana dikembalikan (reversal ledger).
 * Kantin berasal dari keanggotaan pengelola (UserCanteenRole), bukan input klien; setiap
 * keputusan wajib catatan dan tercatat di audit log.
 */
new class extends Component
{
    public int $canteenId = 0;

    /** @var array<string, string> key "payment-{id}" / "refund-{id}" => catatan */
    public array $notes = [];

    public ?string $flash = null;

    public function booted(): void
    {
        $user = Auth::user();
        $id = $user === null ? null : UserCanteenRole::query()
            ->where('user_id', $user->id)
            ->whereIn('role', ['owner', 'manager', 'finance'])
            ->value('canteen_id');
        abort_if($id === null, 403);

        $this->canteenId = (int) $id;
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Payment> */
    #[Computed]
    public function reviews(): \Illuminate\Database\Eloquent\Collection
    {
        return Payment::query()->with('order:id,order_number,canteen_id,status,grand_total_amount')
            ->where('status', 'needs_review')
            ->whereHas('order', fn ($q) => $q->where('canteen_id', $this->canteenId))
            ->orderBy('id')->get();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, TenantOrder> */
    #[Computed]
    public function refunds(): \Illuminate\Database\Eloquent\Collection
    {
        return TenantOrder::query()->withoutGlobalScope('tenant')
            ->with(['order:id,order_number,canteen_id', 'tenant:id,display_name'])
            ->where('status', 'cancelled')->where('refund_status', 'pending')
            ->whereHas('order', fn ($q) => $q->where('canteen_id', $this->canteenId))
            ->orderBy('cancelled_at')->get();
    }

    /** @return \Illuminate\Support\Collection<int, AuditLog> */
    #[Computed]
    public function history(): \Illuminate\Support\Collection
    {
        return AuditLog::query()
            ->whereIn('action', ['review_accepted', 'review_refunded', 'refunded'])
            ->where('canteen_id', $this->canteenId)
            ->latest('id')->limit(10)->get();
    }

    /** Alasan penandaan dari audit log (nominal tidak cocok / dibayar setelah batal). */
    public function reason(Payment $payment): string
    {
        $log = AuditLog::query()->where('entity', 'payment')->where('entity_id', (string) $payment->id)
            ->whereIn('action', ['amount_mismatch', 'paid_after_cancel'])->latest('id')->first();

        return match ($log?->action) {
            'amount_mismatch' => 'Nominal tidak cocok: diterima Rp'.number_format((int) ($log->after['received'] ?? 0), 0, ',', '.')
                .', tagihan Rp'.number_format((int) ($log->after['expected'] ?? 0), 0, ',', '.'),
            'paid_after_cancel' => 'Dibayar setelah pesanan dibatalkan',
            default => 'Perlu ditinjau',
        };
    }

    public function accept(int $paymentId): void
    {
        $this->decide(fn (FollowUpService $s) => $s->acceptPayment(Payment::query()->findOrFail($paymentId), $this->canteenId, Auth::user(), $this->notes['payment-'.$paymentId] ?? ''),
            'Pembayaran diterima dan pesanan diteruskan ke dapur.');
    }

    public function refundPayment(int $paymentId): void
    {
        $this->decide(fn (FollowUpService $s) => $s->refundPayment(Payment::query()->findOrFail($paymentId), $this->canteenId, Auth::user(), $this->notes['payment-'.$paymentId] ?? ''),
            'Pembayaran ditandai dana dikembalikan.');
    }

    public function refundTenantOrder(int $tenantOrderId): void
    {
        $this->decide(fn (FollowUpService $s) => $s->refundTenantOrder(TenantOrder::query()->withoutGlobalScope('tenant')->findOrFail($tenantOrderId), $this->canteenId, Auth::user(), $this->notes['refund-'.$tenantOrderId] ?? ''),
            'Pengembalian dana tercatat; pendapatan tenant dibalik di ledger.');
    }

    private function decide(\Closure $action, string $message): void
    {
        $this->resetErrorBag();
        try {
            $action(app(FollowUpService::class));
            $this->flash = $message;
            $this->notes = [];
            unset($this->reviews, $this->refunds, $this->history);
        } catch (FollowUpException $e) {
            $this->addError('followUp', $e->getMessage());
        }
    }
};
?>

@php($rupiah = fn (int $v): string => 'Rp'.number_format($v, 0, ',', '.'))
@php($orderStatus = ['awaiting_payment' => 'menunggu pembayaran', 'paid' => 'dibayar', 'cancelled' => 'dibatalkan'])
<div class="space-y-8">
    @if ($flash)
        <div class="border-l-4 border-green-700 bg-green-50 px-4 py-2 text-sm font-semibold text-green-800 dark:bg-green-950/40 dark:text-green-300" role="status">{{ $flash }}</div>
    @endif
    @error('followUp')
        <div class="border-l-4 border-red-600 bg-red-50 px-4 py-2 text-sm font-semibold text-red-800 dark:bg-red-950/40 dark:text-red-300" role="alert">{{ $message }}</div>
    @enderror

    <section data-test="reviews">
        <h2 class="mb-2 flex items-center gap-2 text-sm font-extrabold uppercase tracking-wider">Pembayaran perlu ditinjau
            <span @class(['px-2 text-xs text-white', 'bg-red-600' => $this->reviews->isNotEmpty(), 'bg-zinc-400' => $this->reviews->isEmpty()])>{{ $this->reviews->count() }}</span></h2>
        @forelse ($this->reviews as $payment)
            <article wire:key="payment-{{ $payment->id }}" class="mb-3 border-2 border-zinc-900 bg-white p-4 dark:border-zinc-100 dark:bg-zinc-900">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="font-bold">#{{ $payment->order->order_number }} · {{ $payment->payment_reference }}</p>
                    <p class="text-sm font-semibold text-amber-700">{{ $this->reason($payment) }}</p>
                </div>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Status pesanan: {{ $orderStatus[$payment->order->status] ?? $payment->order->status }} · Tagihan {{ $rupiah((int) $payment->amount) }}</p>
                <textarea wire:model="notes.payment-{{ $payment->id }}" rows="2" maxlength="500" placeholder="Catatan tindak lanjut (wajib), mis. hasil konfirmasi ke pelanggan…"
                    class="mt-3 block w-full border border-zinc-400 px-3 py-2 text-sm dark:bg-zinc-950"></textarea>
                <div class="mt-3 flex flex-wrap gap-2">
                    @if ($payment->order->status === 'awaiting_payment')
                        <button type="button" wire:click="accept({{ $payment->id }})" wire:loading.attr="disabled" class="bg-green-700 px-4 py-2 text-sm font-bold text-white">Terima pembayaran</button>
                    @endif
                    <button type="button" wire:click="refundPayment({{ $payment->id }})" wire:loading.attr="disabled" class="border-2 border-zinc-900 px-4 py-2 text-sm font-bold dark:border-zinc-100">Dana dikembalikan</button>
                </div>
            </article>
        @empty
            <x-empty-state title="Tidak ada pembayaran yang perlu ditinjau" description="Callback bernominal tidak cocok atau dibayar setelah pesanan batal akan muncul di sini." />
        @endforelse
    </section>

    <section data-test="refunds">
        <h2 class="mb-2 flex items-center gap-2 text-sm font-extrabold uppercase tracking-wider">Pengembalian dana dari dapur
            <span @class(['px-2 text-xs text-white', 'bg-red-600' => $this->refunds->isNotEmpty(), 'bg-zinc-400' => $this->refunds->isEmpty()])>{{ $this->refunds->count() }}</span></h2>
        @forelse ($this->refunds as $tenantOrder)
            <article wire:key="refund-{{ $tenantOrder->id }}" class="mb-3 border-2 border-zinc-900 bg-white p-4 dark:border-zinc-100 dark:bg-zinc-900">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="font-bold">#{{ $tenantOrder->order->order_number }} · {{ $tenantOrder->tenant->display_name }}</p>
                    <p class="text-lg font-extrabold">{{ $rupiah((int) $tenantOrder->subtotal_amount + (int) $tenantOrder->tax_amount + (int) $tenantOrder->service_fee_amount) }}</p>
                </div>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Dibatalkan dapur: {{ KitchenService::CANCEL_REASONS[$tenantOrder->cancel_reason] ?? $tenantOrder->cancel_reason }}
                    · pendapatan tenant {{ $rupiah((int) $tenantOrder->net_amount) }} akan dibalik</p>
                <textarea wire:model="notes.refund-{{ $tenantOrder->id }}" rows="2" maxlength="500" placeholder="Catatan (wajib), mis. dikembalikan tunai di kasir / transfer ke pelanggan…"
                    class="mt-3 block w-full border border-zinc-400 px-3 py-2 text-sm dark:bg-zinc-950"></textarea>
                <button type="button" wire:click="refundTenantOrder({{ $tenantOrder->id }})" wire:loading.attr="disabled" class="mt-3 bg-red-600 px-4 py-2 text-sm font-bold text-white">Tandai dana dikembalikan</button>
            </article>
        @empty
            <x-empty-state title="Tidak ada pengembalian dana" description="Pesanan yang dibatalkan dapur setelah dibayar akan muncul di sini." />
        @endforelse
    </section>

    <section>
        <h2 class="mb-2 text-sm font-extrabold uppercase tracking-wider">Riwayat keputusan</h2>
        <ul class="divide-y divide-zinc-200 border border-zinc-200 bg-white text-sm dark:divide-zinc-800 dark:border-zinc-800 dark:bg-zinc-900">
            @forelse ($this->history as $log)
                <li wire:key="log-{{ $log->id }}" class="flex justify-between gap-3 px-3 py-2">
                    <span>{{ ['review_accepted' => 'Pembayaran diterima', 'review_refunded' => 'Pembayaran dikembalikan', 'refunded' => 'Dana sub-pesanan dikembalikan'][$log->action] }} — {{ $log->after['note'] ?? '' }}</span>
                    <span class="shrink-0 text-zinc-500">{{ $log->logged_at?->setTimezone(config('app.display_timezone'))->format('d M H.i') }}</span>
                </li>
            @empty
                <li class="px-3 py-2 text-zinc-500">Belum ada keputusan.</li>
            @endforelse
        </ul>
    </section>
</div>
