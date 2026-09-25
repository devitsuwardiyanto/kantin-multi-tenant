<?php

use App\Models\Tenant;
use App\Models\UserTenantRole;
use App\Modules\Reporting\Services\TenantReconciliation;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * UC-18 Lihat Rekonsiliasi Bagi Hasil: ringkasan periode dari ledger append-only, entri per
 * transaksi + referensi pembayaran, penelusuran ke pesanan asal (langkah 4), dan pembayaran
 * "perlu peninjauan" yang tidak dihitung ke saldo (alur 3a). Hanya-baca.
 */
new class extends Component
{
    public int $tenantId = 0;

    public string $tenantName = '';

    /** Periode bulan, format Y-m. */
    public string $month = '';

    public ?int $openOrderId = null;

    public function mount(int $tenantId): void
    {
        $this->tenantId = $tenantId;
        $this->month = CarbonImmutable::now((string) config('app.display_timezone'))->format('Y-m');
    }

    public function booted(): void
    {
        $user = Auth::user();
        $isMember = $user !== null && UserTenantRole::query()
            ->where('user_id', $user->id)->where('tenant_id', $this->tenantId)->exists();
        abort_unless($isMember, 403);

        $tenant = Tenant::query()->findOrFail($this->tenantId);
        $this->tenantName = (string) $tenant->display_name;
        app(TenantContext::class)->set($tenant);
    }

    /** @return list<string> 12 bulan terakhir (Y-m) */
    #[Computed]
    public function months(): array
    {
        $now = CarbonImmutable::now((string) config('app.display_timezone'))->startOfMonth();

        return array_map(fn (int $i): string => $now->subMonthsNoOverflow($i)->format('Y-m'), range(0, 11));
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function recon(): array
    {
        $month = in_array($this->month, $this->months(), true) ? $this->month : $this->months()[0];

        return app(TenantReconciliation::class)->period($this->tenantId, CarbonImmutable::createFromFormat('!Y-m', $month));
    }

    /** @return array<string, mixed>|null */
    #[Computed]
    public function detail(): ?array
    {
        return $this->openOrderId === null ? null : app(TenantReconciliation::class)->orderDetail($this->tenantId, $this->openOrderId);
    }

    public function open(int $orderId): void
    {
        $this->openOrderId = $this->openOrderId === $orderId ? null : $orderId;
    }
};
?>

<div class="space-y-5">
    @php($rupiah = fn (int $n) => 'Rp'.number_format($n, 0, ',', '.'))
    @php($recon = $this->recon)

    <div class="flex flex-wrap items-center gap-3 border-b-2 border-zinc-900 pb-4 dark:border-zinc-100">
        <div class="flex items-center gap-2 text-lg font-extrabold uppercase tracking-wider">
            <span class="size-4 bg-red-600"></span> {{ $tenantName }} · Rekonsiliasi
        </div>
        <label class="ms-auto text-sm font-bold">Periode:
            <select wire:model.live="month" class="border-2 border-zinc-900 px-3 py-1.5 font-bold dark:border-zinc-100 dark:bg-zinc-900">
                @foreach ($this->months as $m)
                    <option value="{{ $m }}">{{ \Carbon\CarbonImmutable::createFromFormat('!Y-m', $m)->locale('id')->translatedFormat('F Y') }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="border-2 border-zinc-900 bg-white p-4 dark:border-zinc-100 dark:bg-zinc-900">
            <p class="text-xs font-bold uppercase tracking-wider text-zinc-500">Pendapatan kotor</p>
            <p class="text-2xl font-extrabold">{{ $rupiah($recon['gross']) }}</p>
        </div>
        <div class="border-2 border-zinc-900 bg-white p-4 dark:border-zinc-100 dark:bg-zinc-900">
            <p class="text-xs font-bold uppercase tracking-wider text-zinc-500">Komisi pengelola{{ $recon['commission_rate'] !== null ? ' '.rtrim(rtrim(number_format($recon['commission_rate'], 2, ',', ''), '0'), ',').'%' : '' }}</p>
            <p class="text-2xl font-extrabold text-red-600">– {{ $rupiah($recon['commission']) }}</p>
            @if ($recon['other'] > 0)
                <p class="text-sm font-semibold text-zinc-500">Biaya lain (pembalikan): – {{ $rupiah($recon['other']) }}</p>
            @endif
        </div>
        <div class="border-2 border-zinc-900 bg-white p-4 dark:border-zinc-100 dark:bg-zinc-900">
            <p class="text-xs font-bold uppercase tracking-wider text-zinc-500">Pendapatan bersih</p>
            <p class="text-2xl font-extrabold">{{ $rupiah($recon['net']) }}</p>
        </div>
        <div class="border-2 border-zinc-900 bg-zinc-900 p-4 text-white dark:border-zinc-100">
            <p class="text-xs font-bold uppercase tracking-wider text-zinc-300">Saldo tersedia</p>
            <p class="text-2xl font-extrabold">{{ $rupiah($recon['available']) }}</p>
            <p class="text-xs font-semibold {{ $recon['matches'] ? 'text-green-300' : 'text-red-300' }}">
                {{ $recon['matches'] ? '= Σ ledger ('.$recon['ledger_entries'].' entri) ✓' : 'Tidak sama dengan Σ ledger ('.$rupiah($recon['ledger_available']).')' }}
            </p>
        </div>
    </div>

    <div class="overflow-x-auto border-2 border-zinc-900 bg-white dark:border-zinc-100 dark:bg-zinc-900">
        <table class="w-full min-w-[48rem] text-left text-sm">
            <thead class="border-b-2 border-zinc-900 text-xs font-bold uppercase tracking-wider text-zinc-500 dark:border-zinc-100">
                <tr>
                    <th class="px-4 py-3">Tanggal</th><th class="px-4 py-3">Referensi</th><th class="px-4 py-3">Pesanan</th>
                    <th class="px-4 py-3">Kotor</th><th class="px-4 py-3">Komisi</th><th class="px-4 py-3">Bersih (kredit)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($recon['review'] as $review)
                    <tr wire:key="review-{{ $review['reference'] }}" class="border-b border-zinc-200 bg-red-50 dark:border-zinc-800 dark:bg-red-950/30">
                        <td class="px-4 py-3">{{ \Carbon\CarbonImmutable::parse($review['at'])->locale('id')->translatedFormat('j M · H.i') }}</td>
                        <td class="px-4 py-3 font-bold">{{ $review['reference'] }}</td>
                        <td class="px-4 py-3 font-bold text-red-600">#{{ $review['order_number'] }}</td>
                        <td class="px-4 py-3">{{ $rupiah($review['gross']) }}</td>
                        <td colspan="2" class="px-4 py-3"><span class="bg-red-600 px-2 py-0.5 text-xs font-extrabold uppercase text-white">Perlu peninjauan</span> nominal callback tidak cocok — tidak dihitung ke saldo</td>
                    </tr>
                @endforeach
                @forelse ($recon['rows'] as $i => $row)
                    <tr wire:key="row-{{ $i }}-{{ $row['reference'] }}" class="border-b border-zinc-200 dark:border-zinc-800 {{ $row['kind'] === 'withdrawal' ? 'text-zinc-500' : '' }}">
                        <td class="px-4 py-3">{{ \Carbon\CarbonImmutable::parse($row['at'])->locale('id')->translatedFormat('j M · H.i') }}</td>
                        <td class="px-4 py-3 font-bold">{{ $row['reference'] }}</td>
                        <td class="px-4 py-3">
                            @if ($row['order_id'])
                                <button type="button" wire:click="open({{ $row['order_id'] }})" class="font-bold text-red-600">#{{ $row['order_number'] }}</button>
                                @if ($row['refund_pending'])
                                    <span class="block text-xs font-semibold text-amber-700">Dibatalkan dapur — pengembalian dana menunggu pengelola</span>
                                @endif
                            @else
                                {{ $row['label'] }}
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $row['kind'] === 'sale' ? $rupiah($row['gross']) : '—' }}</td>
                        <td class="px-4 py-3">{{ $row['kind'] === 'sale' ? $rupiah($row['commission']) : '—' }}</td>
                        <td class="px-4 py-3 font-bold {{ $row['net'] < 0 ? 'text-red-600' : '' }}">
                            @if ($row['net'] < 0)
                                – {{ $rupiah(-$row['net']) }} (debit)
                            @elseif ($row['net'] === 0 && $row['held'] !== 0)
                                <span class="font-semibold">dari saldo tertahan {{ $rupiah(abs($row['held'])) }}</span>
                            @else
                                {{ $rupiah($row['net']) }}
                            @endif
                            @if ($row['other'] > 0)
                                <span class="block text-xs font-semibold text-zinc-500">pembalikan – {{ $rupiah($row['other']) }}</span>
                            @endif
                        </td>
                    </tr>
                    @if ($row['order_id'] && $openOrderId === $row['order_id'] && $this->detail)
                        <tr wire:key="detail-{{ $row['order_id'] }}" class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950">
                            <td colspan="6" class="px-6 py-3">
                                <p class="text-xs font-bold uppercase tracking-wider text-zinc-500">Rincian pesanan #{{ $this->detail['order_number'] }} · dipesan {{ $this->detail['placed_at'] }} · status {{ $this->detail['status'] }}</p>
                                <ul class="mt-2 space-y-1">
                                    @foreach ($this->detail['items'] as $item)
                                        <li class="flex justify-between gap-4"><span>{{ $item['quantity'] }}× {{ $item['name'] }}@if ($item['note']) <em class="text-zinc-500">— {{ $item['note'] }}</em>@endif</span><span>{{ $rupiah($item['line_total']) }}</span></li>
                                    @endforeach
                                </ul>
                                <p class="mt-2 text-right text-sm">Subtotal {{ $rupiah($this->detail['subtotal']) }} · komisi {{ $rupiah($this->detail['commission']) }} · <strong>bersih {{ $rupiah($this->detail['net']) }}</strong></p>
                            </td>
                        </tr>
                    @endif
                @empty
                    @if ($recon['review'] === [])
                        <tr><td colspan="6" class="px-4 py-6 text-center text-zinc-400">Belum ada entri ledger pada periode ini.</td></tr>
                    @endif
                @endforelse
            </tbody>
        </table>
        <p class="px-4 py-3 text-xs text-zinc-500">Ledger bersifat append-only; klik nomor pesanan untuk menelusuri rincian pesanan asal. Saldo tersedia = jumlah seluruh entri ledger terselesaikan.</p>
    </div>
</div>
