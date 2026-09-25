<x-layouts.customer :title="'Status Pesanan'">
    @php($rupiah = fn (int $n) => 'Rp '.number_format($n, 0, ',', '.'))

    <div class="mx-auto max-w-2xl space-y-6">
        {{-- UC-07: layar pembayaran QRIS tampil paling atas selama pesanan menunggu pembayaran. --}}
        <livewire:payments::order-payment :canteen-slug="request()->route('canteen')" />
        {{-- UC-09: pelacakan status per tenant (realtime + polling cadangan). --}}
        <livewire:ordering::order-tracker :canteen-slug="request()->route('canteen')" />

        <div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
            <dl class="space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-zinc-500">Subtotal</dt><dd>{{ $rupiah($order->subtotal_amount) }}</dd></div>
                <div class="flex justify-between"><dt class="text-zinc-500">Pajak</dt><dd>{{ $rupiah($order->tax_amount) }}</dd></div>
                <div class="flex justify-between"><dt class="text-zinc-500">Biaya layanan</dt><dd>{{ $rupiah($order->service_fee_amount) }}</dd></div>
                <div class="mt-2 flex justify-between border-t border-zinc-200 pt-2 text-base font-bold dark:border-zinc-800"><dt>Total</dt><dd>{{ $rupiah($order->grand_total_amount) }}</dd></div>
            </dl>
        </div>
    </div>
</x-layouts.customer>
