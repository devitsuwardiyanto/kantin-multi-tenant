<x-layouts.customer :title="'Status Pesanan'">
    @php($rupiah = fn (int $n) => 'Rp '.number_format($n, 0, ',', '.'))

    <div class="mx-auto max-w-2xl space-y-6">
        <div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs text-zinc-500">Nomor Pesanan</p>
                    <p class="font-semibold">{{ $order->order_number }}</p>
                </div>
                <x-status-badge :status="$order->status === 'paid' ? 'active' : 'pending'">{{ str_replace('_', ' ', $order->status) }}</x-status-badge>
            </div>
            @if (($order->table_snapshot['label'] ?? null))
                <p class="mt-2 text-sm text-zinc-500">Meja: {{ $order->table_snapshot['label'] }} ({{ $order->table_snapshot['code'] ?? '' }})</p>
            @endif
        </div>

        @foreach ($order->tenantOrders as $tenantOrder)
            <div wire:key="to-{{ $tenantOrder->id }}" class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="font-semibold">{{ $tenantOrder->tenant->display_name }}</h2>
                    <x-status-badge :status="'pending'">{{ $tenantOrder->status }}</x-status-badge>
                </div>
                <ul class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach ($tenantOrder->items as $item)
                        <li class="flex items-start justify-between gap-3 py-2">
                            <div class="min-w-0">
                                <p class="truncate font-medium">{{ $item->quantity }}× {{ $item->name_snapshot }}</p>
                                @foreach ($item->modifiers as $modifier)
                                    <p class="truncate text-xs text-zinc-500">+ {{ $modifier->option_name_snapshot }}</p>
                                @endforeach
                            </div>
                            <p class="shrink-0 font-semibold">{{ $rupiah($item->line_total) }}</p>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-2 text-right text-sm text-zinc-500">Subtotal tenant: {{ $rupiah($tenantOrder->subtotal_amount) }}</p>
            </div>
        @endforeach

        <div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
            <dl class="space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-zinc-500">Subtotal</dt><dd>{{ $rupiah($order->subtotal_amount) }}</dd></div>
                <div class="flex justify-between"><dt class="text-zinc-500">Pajak</dt><dd>{{ $rupiah($order->tax_amount) }}</dd></div>
                <div class="flex justify-between"><dt class="text-zinc-500">Biaya layanan</dt><dd>{{ $rupiah($order->service_fee_amount) }}</dd></div>
                <div class="mt-2 flex justify-between border-t border-zinc-200 pt-2 text-base font-bold dark:border-zinc-800"><dt>Total</dt><dd>{{ $rupiah($order->grand_total_amount) }}</dd></div>
            </dl>
        </div>

        <livewire:order-payment :canteen-slug="request()->route('canteen')" />
    </div>
</x-layouts.customer>
