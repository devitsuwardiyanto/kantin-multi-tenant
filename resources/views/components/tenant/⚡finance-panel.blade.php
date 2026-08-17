<?php

use App\Models\Tenant;
use App\Models\TenantBalance;
use App\Models\TenantBankAccount;
use App\Models\UserTenantRole;
use App\Models\Withdrawal;
use App\Modules\Payments\Exceptions\WithdrawalException;
use App\Modules\Payments\Services\WithdrawalService;
use App\Modules\Reporting\Services\TenantLedgerReport;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Panel keuangan tenant: saldo, ringkasan & rekonsiliasi dari ledger, dan permintaan penarikan.
 * tenantId prop publik TIDAK dipercaya: booted() re-verify membership + set TenantContext.
 */
new class extends Component
{
    public int $tenantId = 0;

    public string $tenantSlug = '';

    public int $amount = 0;

    public ?int $bankAccountId = null;

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
    public function accounts(): \Illuminate\Database\Eloquent\Collection
    {
        return TenantBankAccount::query()->where('tenant_id', $this->tenantId)->where('status', 'verified')->get();
    }

    #[Computed]
    public function withdrawals(): \Illuminate\Database\Eloquent\Collection
    {
        return Withdrawal::query()->orderByDesc('id')->limit(10)->get();
    }

    public function requestWithdrawal(): void
    {
        $data = $this->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'bankAccountId' => ['required', 'integer'],
        ]);

        $account = TenantBankAccount::query()->find($data['bankAccountId']);
        if ($account === null || (int) $account->tenant_id !== $this->tenantId) {
            $this->addError('bankAccountId', 'Rekening tidak valid.');

            return;
        }

        try {
            app(WithdrawalService::class)->request($account, (int) $data['amount'], Auth::user());
            $this->reset(['amount', 'bankAccountId']);
            session()->flash('status', 'Permintaan penarikan diajukan.');
        } catch (WithdrawalException $e) {
            $this->addError('amount', $e->getMessage());
        }

        unset($this->balance, $this->summary, $this->reconcile, $this->withdrawals);
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

    <div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
        <h2 class="mb-3 font-semibold">Ajukan Penarikan</h2>
        @if ($this->accounts->isEmpty())
            <p class="text-sm text-zinc-500">Belum ada rekening terverifikasi. Hubungi pengelola kantin.</p>
        @else
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                <x-input name="amount" label="Nominal (Rp)" type="number" wire:model="amount" />
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Rekening</label>
                    <select wire:model="bankAccountId" class="min-h-11 rounded-lg border border-zinc-300 bg-white px-3 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                        <option value="">Pilih rekening</option>
                        @foreach ($this->accounts as $account)
                            <option value="{{ $account->id }}" wire:key="acc-{{ $account->id }}">{{ $account->bank_code }} ••{{ $account->account_last4 }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end">
                    <x-button type="button" wire:click="requestWithdrawal">Ajukan</x-button>
                </div>
            </div>
            @error('amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            @error('bankAccountId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        @endif
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
