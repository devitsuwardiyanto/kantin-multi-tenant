<?php

use App\Models\Tenant;
use App\Models\TenantBalance;
use App\Models\UserTenantRole;
use App\Models\Withdrawal;
use App\Modules\Reporting\Services\TenantLedgerReport;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Panel keuangan tenant: saldo, ringkasan & rekonsiliasi dari ledger, riwayat penarikan, dan
 * tautan ke laporan (UC-16/17), rekonsiliasi (UC-18), serta pengajuan penarikan (UC-20).
 * tenantId prop publik TIDAK dipercaya: booted() re-verify membership + set TenantContext.
 */
new class extends Component
{
    public int $tenantId = 0;

    public string $tenantSlug = '';

    public function mount(int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function booted(): void
    {
        $user = Auth::user();
        $isMember = $user !== null && UserTenantRole::query()
            ->where('user_id', $user->id)->where('tenant_id', $this->tenantId)->exists();
        abort_unless($isMember, 403);

        $tenant = Tenant::query()->findOrFail($this->tenantId);
        $this->tenantSlug = (string) $tenant->slug;
        app(TenantContext::class)->set($tenant);
    }

    #[Computed]
    public function balance(): TenantBalance
    {
        return TenantBalance::query()->firstOrNew(['tenant_id' => $this->tenantId]);
    }

    /** @return array{gross_sales: int, commission: int, net: int, withdrawn: int, entries: int} */
    #[Computed]
    public function summary(): array
    {
        return app(TenantLedgerReport::class)->summary($this->tenantId);
    }

    /** @return array{ledger_available: int, ledger_held: int, balance_available: int, balance_held: int, matches: bool} */
    #[Computed]
    public function reconcile(): array
    {
        return app(TenantLedgerReport::class)->reconcile($this->tenantId);
    }

    #[Computed]
    public function withdrawals(): \Illuminate\Database\Eloquent\Collection
    {
        return Withdrawal::query()->orderByDesc('id')->limit(10)->get();
    }
};
?>

<div class="space-y-6">
    @php($rupiah = fn (int $n) => 'Rp '.number_format($n, 0, ',', '.'))

    @if (session('status'))
        <div class="rounded-lg bg-green-100 px-4 py-2 text-sm text-green-800 dark:bg-green-900/40 dark:text-green-300">{{ session('status') }}</div>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
            <p class="text-xs text-zinc-500">Saldo Tersedia</p>
            <p class="text-2xl font-bold">{{ $rupiah((int) $this->balance->available_amount) }}</p>
        </div>
        <div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
            <p class="text-xs text-zinc-500">Ditahan</p>
            <p class="text-2xl font-bold">{{ $rupiah((int) $this->balance->held_amount) }}</p>
        </div>
        <div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
            <p class="text-xs text-zinc-500">Penjualan Kotor</p>
            <p class="text-2xl font-bold">{{ $rupiah($this->summary['gross_sales']) }}</p>
        </div>
        <div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
            <p class="text-xs text-zinc-500">Komisi</p>
            <p class="text-2xl font-bold">{{ $rupiah($this->summary['commission']) }}</p>
        </div>
    </div>

    <div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold">Rekonsiliasi Ledger</h2>
            <x-status-badge :status="$this->reconcile['matches'] ? 'active' : 'suspended'">{{ $this->reconcile['matches'] ? 'cocok' : 'selisih' }}</x-status-badge>
        </div>
        <p class="mt-2 text-sm text-zinc-500">Ledger available {{ $rupiah($this->reconcile['ledger_available']) }} · saldo {{ $rupiah($this->reconcile['balance_available']) }}; held ledger {{ $rupiah($this->reconcile['ledger_held']) }} · saldo {{ $rupiah($this->reconcile['balance_held']) }}.</p>
        <a href="{{ route('tenant.finance.export', ['tenant' => $this->tenantSlug]) }}" class="mt-2 inline-block text-sm underline">Ekspor CSV ledger</a>
    </div>

    <div class="flex flex-wrap gap-3 text-sm font-bold">
        <a href="{{ route('tenant.reports', ['tenant' => $this->tenantSlug]) }}" class="border-2 border-zinc-900 px-4 py-2 dark:border-zinc-100">Laporan penjualan →</a>
        <a href="{{ route('tenant.reconciliation', ['tenant' => $this->tenantSlug]) }}" class="border-2 border-zinc-900 px-4 py-2 dark:border-zinc-100">Rekonsiliasi →</a>
        <a href="{{ route('tenant.withdrawals', ['tenant' => $this->tenantSlug]) }}" class="bg-red-600 px-4 py-2 text-white">Ajukan penarikan →</a>
    </div>

    <div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
        <h2 class="mb-3 font-semibold">Riwayat Penarikan</h2>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[28rem] text-left text-sm">
                <thead class="text-zinc-500"><tr><th class="py-1">Nominal</th><th class="py-1">Status</th></tr></thead>
                <tbody>
                    @forelse ($this->withdrawals as $withdrawal)
                        <tr wire:key="wd-{{ $withdrawal->id }}" class="border-t border-zinc-200 dark:border-zinc-800">
                            <td class="py-2">{{ $rupiah((int) $withdrawal->amount) }}</td>
                            <td class="py-2"><x-status-badge :status="$withdrawal->status === 'paid' ? 'active' : ($withdrawal->status === 'rejected' ? 'suspended' : 'pending')">{{ $withdrawal->status }}</x-status-badge></td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="py-3 text-zinc-400">Belum ada penarikan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
