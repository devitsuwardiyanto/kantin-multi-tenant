<?php

use App\Models\ReportExport;
use App\Models\Tenant;
use App\Models\UserTenantRole;
use App\Modules\Reporting\Exceptions\ReportExportException;
use App\Modules\Reporting\Services\ReportExportService;
use App\Modules\Reporting\Services\TenantSalesReport;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * UC-16 Lihat Laporan Penjualan + UC-17 Ekspor Laporan (extension point "permintaan ekspor").
 * tenantId prop publik TIDAK dipercaya: booted() re-verify membership + set TenantContext,
 * sehingga agregasi dan ekspor selalu milik tenant aktif.
 */
new class extends Component
{
    public int $tenantId = 0;

    public string $tenantName = '';

    public string $from = '';

    public string $to = '';

    public ?string $exportMessage = null;

    public function mount(int $tenantId): void
    {
        $this->tenantId = $tenantId;
        $today = CarbonImmutable::now((string) config('app.display_timezone'));
        $this->from = $today->startOfMonth()->toDateString();
        $this->to = $today->toDateString();
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

    /** Langkah 5: rentang cepat; seluruh metrik dihitung ulang. */
    public function preset(string $range): void
    {
        $today = CarbonImmutable::now((string) config('app.display_timezone'));
        [$from, $to] = match ($range) {
            '7d' => [$today->subDays(6), $today],
            '30d' => [$today->subDays(29), $today],
            'last-month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            default => [$today->startOfMonth(), $today],
        };
        $this->from = $from->toDateString();
        $this->to = $to->toDateString();
        $this->resetErrorBag('range');
    }

    /** Alur 2a (UC-16): lompat ke bulan transaksi terakhir yang disarankan. */
    public function useRange(string $from, string $to): void
    {
        $this->from = $from;
        $this->to = $to;
        $this->resetErrorBag('range');
    }

    /**
     * @return array{from: CarbonImmutable, to: CarbonImmutable}|null
     */
    private function range(): ?array
    {
        $parse = function (string $date): ?CarbonImmutable {
            try {
                return CarbonImmutable::createFromFormat('!Y-m-d', $date) ?: null;
            } catch (\InvalidArgumentException) {
                return null;
            }
        };
        $from = $parse($this->from);
        $to = $parse($this->to);

        if ($from === null || $to === null || $from->greaterThan($to)) {
            return null;
        }

        return ['from' => $from, 'to' => $to];
    }

    /** Validasi rentang sebelum render agar pesan galat ikut tampil. */
    public function updated(string $property): void
    {
        if (in_array($property, ['from', 'to'], true)) {
            $this->resetErrorBag('range');
            if ($this->range() === null) {
                $this->addError('range', 'Rentang tanggal tidak valid.');
            }
        }
    }

    /** @return array<string, mixed>|null */
    #[Computed]
    public function report(): ?array
    {
        $range = $this->range();

        return $range === null ? null : app(TenantSalesReport::class)->summary($this->tenantId, $range['from'], $range['to']);
    }

    #[Computed]
    public function exports(): \Illuminate\Database\Eloquent\Collection
    {
        return ReportExport::query()->where('requested_by', Auth::id())->orderByDesc('id')->limit(5)->get();
    }

    /** UC-17 langkah 1–2: pilih format lalu antrikan pembuatan berkas. */
    public function export(string $format): void
    {
        $range = $this->range();
        if ($range === null) {
            $this->addError('range', 'Rentang tanggal tidak valid.');

            return;
        }

        try {
            $export = app(ReportExportService::class)->request(Tenant::query()->findOrFail($this->tenantId), Auth::user(), $format, $range['from'], $range['to']);
            $this->exportMessage = $export->notify_by_mail
                ? 'Data sangat besar ('.$export->row_count.' transaksi): berkas diproses di latar belakang dan tautannya dikirim ke surel Anda.'
                : 'Ekspor '.strtoupper($format).' sedang dibuat. Tautan unduh muncul di bawah saat berkas siap.';
        } catch (ReportExportException $e) {
            $this->addError('export', $e->getMessage());
        }

        unset($this->exports);
    }

    public function downloadUrl(ReportExport $export): ?string
    {
        return app(ReportExportService::class)->downloadUrl($export);
    }
};
?>

<div class="space-y-5" @if ($this->exports->contains('status', 'queued')) wire:poll.3s @endif>
    @php($rupiah = fn (int $n) => 'Rp'.number_format($n, 0, ',', '.'))
    @php($report = $this->report)

    <div class="flex flex-wrap items-end gap-3 border-b-2 border-zinc-900 pb-4 dark:border-zinc-100">
        <div class="flex items-center gap-2 text-lg font-extrabold uppercase tracking-wider">
            <span class="size-4 bg-red-600"></span> {{ $tenantName }} · Laporan
        </div>
        <div class="ms-auto flex flex-wrap items-end gap-2">
            <label class="text-xs font-semibold text-zinc-500">Dari
                <input type="date" wire:model.live="from" class="block border-2 border-zinc-900 px-2 py-1.5 text-sm font-bold dark:border-zinc-100 dark:bg-zinc-900" />
            </label>
            <label class="text-xs font-semibold text-zinc-500">Sampai
                <input type="date" wire:model.live="to" class="block border-2 border-zinc-900 px-2 py-1.5 text-sm font-bold dark:border-zinc-100 dark:bg-zinc-900" />
            </label>
            <button type="button" wire:click="export('xlsx')" class="border-2 border-zinc-900 px-4 py-2 text-sm font-bold dark:border-zinc-100">Ekspor .xlsx</button>
            <button type="button" wire:click="export('pdf')" class="border-2 border-red-600 bg-red-600 px-4 py-2 text-sm font-bold text-white">Ekspor PDF</button>
        </div>
    </div>

    <div class="flex flex-wrap gap-2 text-xs font-semibold">
        <button type="button" wire:click="preset('month')" class="border border-zinc-400 px-2 py-1">Bulan ini</button>
        <button type="button" wire:click="preset('7d')" class="border border-zinc-400 px-2 py-1">7 hari</button>
        <button type="button" wire:click="preset('30d')" class="border border-zinc-400 px-2 py-1">30 hari</button>
        <button type="button" wire:click="preset('last-month')" class="border border-zinc-400 px-2 py-1">Bulan lalu</button>
    </div>

    @error('range') <p class="text-sm font-semibold text-red-600">{{ $message }}</p> @enderror
    @error('export') <p class="text-sm font-semibold text-red-600">{{ $message }}</p> @enderror
    @if ($exportMessage)
        <p class="border-l-4 border-red-600 bg-white px-3 py-2 text-sm dark:bg-zinc-900">{{ $exportMessage }}</p>
    @endif

    @if ($this->exports->isNotEmpty())
        <div class="border-2 border-zinc-900 bg-white text-sm dark:border-zinc-100 dark:bg-zinc-900">
            <div class="border-b border-zinc-300 px-4 py-2 text-xs font-extrabold uppercase tracking-wider">Berkas ekspor</div>
            @foreach ($this->exports as $export)
                <div wire:key="export-{{ $export->id }}" class="flex flex-wrap items-center gap-3 border-b border-zinc-200 px-4 py-2 last:border-0 dark:border-zinc-800">
                    <span class="font-bold uppercase">{{ $export->format }}</span>
                    <span class="text-zinc-500">{{ $export->date_from->toDateString() }} s.d. {{ $export->date_to->toDateString() }} · {{ $export->row_count }} transaksi</span>
                    <span class="ms-auto">
                        @if ($url = $this->downloadUrl($export))
                            <a href="{{ $url }}" class="font-bold text-red-600 underline">Unduh</a>
                            <span class="text-xs text-zinc-500">· berlaku s.d. {{ $export->expires_at->setTimezone(config('app.display_timezone'))->format('d M H:i') }}</span>
                        @elseif ($export->status === 'queued')
                            <span class="text-zinc-500">Sedang dibuat…</span>
                        @elseif ($export->status === 'failed')
                            <span class="font-semibold text-red-600">Gagal — coba lagi</span>
                        @else
                            <span class="text-zinc-400">Kedaluwarsa</span>
                        @endif
                    </span>
                </div>
            @endforeach
        </div>
    @endif

    @if ($report !== null && $report['transactions'] === 0)
        <div class="border-2 border-dashed border-zinc-400 px-6 py-10 text-center">
            <p class="text-lg font-bold">Belum ada penjualan pada rentang ini.</p>
            @if ($report['latest_sale_date'])
                @php($latest = \Carbon\CarbonImmutable::parse($report['latest_sale_date']))
                <p class="mt-2 text-sm text-zinc-500">Transaksi terakhir tercatat {{ $latest->locale('id')->translatedFormat('j F Y') }}.</p>
                <button type="button" wire:click="useRange('{{ $latest->startOfMonth()->toDateString() }}', '{{ $latest->endOfMonth()->toDateString() }}')" class="mt-3 border-2 border-zinc-900 px-4 py-2 text-sm font-bold dark:border-zinc-100">
                    Lihat {{ $latest->locale('id')->translatedFormat('F Y') }}
                </button>
            @else
                <p class="mt-2 text-sm text-zinc-500">Coba rentang lain, misalnya “30 hari” atau “Bulan lalu”.</p>
            @endif
        </div>
    @elseif ($report !== null)
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="border-2 border-zinc-900 bg-white p-4 dark:border-zinc-100 dark:bg-zinc-900">
                <p class="text-xs font-bold uppercase tracking-wider text-zinc-500">Total omset</p>
                <p class="text-3xl font-extrabold">{{ $rupiah($report['revenue']) }}</p>
                @if ($report['change_percent'] !== null)
                    <p class="text-sm font-bold {{ $report['change_percent'] >= 0 ? 'text-green-700' : 'text-red-600' }}">
                        {{ $report['change_percent'] >= 0 ? '▲' : '▼' }} {{ str_replace('.', ',', (string) abs($report['change_percent'])) }}% vs periode lalu
                    </p>
                @endif
            </div>
            <div class="border-2 border-zinc-900 bg-white p-4 dark:border-zinc-100 dark:bg-zinc-900">
                <p class="text-xs font-bold uppercase tracking-wider text-zinc-500">Jumlah transaksi</p>
                <p class="text-3xl font-extrabold">{{ $report['transactions'] }}</p>
                <p class="text-sm font-semibold text-zinc-500">{{ $report['days'] }} hari · {{ str_replace('.', ',', (string) $report['per_day']) }}/hari</p>
            </div>
            <div class="border-2 border-zinc-900 bg-white p-4 dark:border-zinc-100 dark:bg-zinc-900">
                <p class="text-xs font-bold uppercase tracking-wider text-zinc-500">Rata-rata / transaksi</p>
                <p class="text-3xl font-extrabold">{{ $rupiah($report['average']) }}</p>
                <p class="text-sm font-semibold text-zinc-500">median {{ $rupiah($report['median']) }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="border-2 border-zinc-900 bg-white p-4 dark:border-zinc-100 dark:bg-zinc-900">
                <p class="mb-3 text-sm font-extrabold uppercase tracking-wider">Menu terlaris</p>
                @php($maxQty = max(1, $report['top_menus'][0]['quantity'] ?? 1))
                <div class="space-y-3">
                    @foreach ($report['top_menus'] as $i => $menu)
                        <div wire:key="top-{{ $i }}">
                            <div class="flex justify-between text-sm font-bold"><span>{{ $menu['name'] }}</span><span>{{ $menu['quantity'] }}</span></div>
                            <div class="mt-1 h-3 bg-red-600" style="width: {{ round($menu['quantity'] / $maxQty * 100) }}%; opacity: {{ 1 - $i * 0.18 }}"></div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="border-2 border-zinc-900 bg-white p-4 dark:border-zinc-100 dark:bg-zinc-900">
                <p class="mb-3 text-sm font-extrabold uppercase tracking-wider">Distribusi pesanan per jam</p>
                @php($maxHour = max(1, ...array_values($report['hourly'])))
                @php($busy = $report['busy'])
                <div class="flex h-48 items-end gap-1">
                    @foreach ($report['hourly'] as $hour => $total)
                        @php($isBusy = $busy !== null && $hour >= $busy['from'] && $hour <= $busy['to'])
                        <div wire:key="hour-{{ $hour }}" class="flex flex-1 flex-col items-center justify-end gap-1" title="{{ sprintf('%02d.00', $hour) }} — {{ $total }} pesanan">
                            <div class="w-full {{ $isBusy ? 'bg-red-600' : 'bg-zinc-300 dark:bg-zinc-700' }}" style="height: {{ max(2, round($total / $maxHour * 170)) }}px"></div>
                            <span class="text-[10px] font-semibold text-zinc-500">{{ sprintf('%02d', $hour) }}</span>
                        </div>
                    @endforeach
                </div>
                @if ($busy !== null)
                    <p class="mt-2 text-sm font-bold text-red-600">Jam sibuk: {{ sprintf('%02d.00', $busy['from']) }}–{{ sprintf('%02d.00', $busy['to'] + 1) }} ({{ $busy['share'] }}% pesanan)</p>
                @endif
            </div>
        </div>
    @endif

    <p class="text-xs text-zinc-500">Laporan dihitung dari sub-pesanan lunas milik tenant ini (tidak memuat tenant lain). Ekspor dibuat lewat antrean; tautan unduh berlaku {{ \App\Modules\Reporting\Services\ReportExportService::LINK_TTL_HOURS }} jam.</p>
</div>
