<?php

use App\Models\Tenant;
use App\Models\TenantBalance;
use App\Models\TenantBankAccount;
use App\Models\UserTenantRole;
use App\Models\Withdrawal;
use App\Modules\Payments\Exceptions\WithdrawalException;
use App\Modules\Payments\Services\WithdrawalService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * UC-20 Ajukan Penarikan Dana: saldo tersedia + tertahan, pengajuan ke rekening terverifikasi
 * (validasi saldo & minimum di dalam transaksi berkunci — WithdrawalService), dan riwayat.
 * tenantId prop publik TIDAK dipercaya: booted() re-verify membership + set TenantContext.
 */
new class extends Component
{
    public int $tenantId = 0;

    public string $tenantName = '';

    public ?int $amount = null;

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
        $this->tenantName = (string) $tenant->display_name;
        app(TenantContext::class)->set($tenant);
    }

    #[Computed]
    public function balance(): TenantBalance
    {
        return TenantBalance::query()->firstOrNew(['tenant_id' => $this->tenantId]);
    }

    /** Rekening tujuan: rekening utama yang sudah diverifikasi pengelola. */
    #[Computed]
    public function account(): ?TenantBankAccount
    {
        return TenantBankAccount::query()->where('tenant_id', $this->tenantId)->where('status', 'verified')->orderByDesc('is_primary')->first();
    }

    #[Computed]
    public function history(): \Illuminate\Database\Eloquent\Collection
    {
        return Withdrawal::query()->orderByDesc('id')->limit(20)->get();
    }

    public function submit(): void
    {
        $this->validate(['amount' => ['required', 'integer', 'min:1']], [
            'amount.required' => 'Isi nominal penarikan.',
            'amount.integer' => 'Nominal harus berupa angka.',
            'amount.min' => 'Nominal penarikan tidak valid.',
        ]);

        $account = $this->account;
        if ($account === null) {
            $this->addError('amount', 'Belum ada rekening terverifikasi. Hubungi pengelola kantin.');

            return;
        }

        try {
            $withdrawal = app(WithdrawalService::class)->request($account, (int) $this->amount, Auth::user());
            $this->reset('amount');
            session()->flash('status', 'Pengajuan '.$withdrawal->reference().' tercatat — menunggu persetujuan pengelola.');
        } catch (WithdrawalException $e) {
            $this->addError('amount', $e->getMessage());
        }

        unset($this->balance, $this->history);
    }
};
?>

<div class="space-y-5">
    @php($rupiah = fn (int $n) => 'Rp'.number_format($n, 0, ',', '.'))
    @php($account = $this->account)

    <div class="flex flex-wrap items-center gap-3 border-b-2 border-zinc-900 pb-4 dark:border-zinc-100">
        <div class="flex items-center gap-2 text-lg font-extrabold uppercase tracking-wider">
            <span class="size-4 bg-red-600"></span> {{ $tenantName }} · Penarikan dana
        </div>
        <span class="ms-auto text-sm font-semibold text-zinc-500">
            @if ($account)
                Rekening: {{ $account->bank_code }} ••••{{ $account->account_last4 }} a.n. {{ $account->account_holder }} ✓ terverifikasi
            @else
                Belum ada rekening terverifikasi
            @endif
        </span>
    </div>

    @if (session('status'))
        <div class="border-l-4 border-green-700 bg-green-50 px-4 py-2 text-sm font-semibold text-green-800 dark:bg-green-950/40 dark:text-green-300">{{ session('status') }}</div>
    @endif

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-5">
        <div class="space-y-5 lg:col-span-2">
            <div class="bg-zinc-900 p-5 text-white">
                <p class="text-xs font-bold uppercase tracking-wider text-zinc-300">Saldo tersedia</p>
                <p class="text-4xl font-extrabold">{{ $rupiah((int) $this->balance->available_amount) }}</p>
                <p class="text-sm font-semibold text-zinc-300">Tertahan (pengajuan aktif): {{ $rupiah((int) $this->balance->held_amount) }}</p>
            </div>

            <form wire:submit="submit" class="space-y-3 border-2 border-zinc-900 bg-white p-5 dark:border-zinc-100 dark:bg-zinc-900">
                <p class="text-sm font-extrabold uppercase tracking-wider">Ajukan penarikan</p>
                <label for="withdraw-amount" class="block text-sm font-semibold text-zinc-500">Nominal (min. {{ $rupiah(\App\Modules\Payments\Services\WithdrawalService::minimum()) }})</label>
                <div class="flex items-center border-2 border-zinc-900 px-3 dark:border-zinc-100">
                    <span class="text-xl font-bold">Rp</span>
                    <input id="withdraw-amount" type="number" inputmode="numeric" min="1" step="1" wire:model="amount" class="w-full border-0 bg-transparent px-2 py-3 text-xl font-bold focus:ring-0" placeholder="0" @disabled($account === null) />
                </div>
                @error('amount') <p class="text-sm font-semibold text-red-600">{{ $message }}</p> @enderror
                <p class="text-sm text-zinc-500">Saat diajukan, saldo dikunci dan hold append-only dibuat atomik. Hanya satu pengajuan aktif per tenant.</p>
                <button type="submit" class="w-full bg-red-600 px-4 py-3 text-base font-bold text-white disabled:opacity-50" @disabled($account === null) wire:loading.attr="disabled">Ajukan penarikan →</button>
            </form>
        </div>

        <div class="border-2 border-zinc-900 bg-white lg:col-span-3 dark:border-zinc-100 dark:bg-zinc-900">
            <p class="border-b-2 border-zinc-900 px-5 py-3 text-sm font-extrabold uppercase tracking-wider dark:border-zinc-100">Riwayat pengajuan</p>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[32rem] text-left text-sm">
                    <thead class="text-xs font-bold uppercase tracking-wider text-zinc-500">
                        <tr><th class="px-5 py-2">Tanggal</th><th class="px-5 py-2">Nominal</th><th class="px-5 py-2">Status</th><th class="px-5 py-2">Catatan</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($this->history as $withdrawal)
                            @php($badge = ['paid' => ['Dicairkan', 'bg-green-700'], 'rejected' => ['Ditolak', 'bg-red-600'], 'investigation' => ['Investigasi', 'bg-amber-600']][$withdrawal->status] ?? ['Menunggu', 'bg-zinc-700'])
                            <tr wire:key="wd-{{ $withdrawal->id }}" class="border-t border-zinc-200 dark:border-zinc-800">
                                <td class="px-5 py-3">{{ $withdrawal->created_at->setTimezone(config('app.display_timezone'))->locale('id')->translatedFormat('d M Y') }}<span class="block text-xs text-zinc-500">{{ $withdrawal->reference() }}</span></td>
                                <td class="px-5 py-3 font-bold">{{ $rupiah((int) $withdrawal->amount) }}</td>
                                <td class="px-5 py-3"><span class="{{ $badge[1] }} px-2 py-1 text-xs font-extrabold uppercase text-white">{{ $badge[0] }}</span></td>
                                <td class="px-5 py-3 text-zinc-600 dark:text-zinc-300">
                                    @if ($withdrawal->status === 'paid')
                                        Bukti transfer ✓
                                    @elseif ($withdrawal->status === 'rejected')
                                        {{ $withdrawal->review_note }}
                                    @elseif ($withdrawal->status === 'investigation')
                                        Ledger sedang diperiksa pengelola
                                    @else
                                        Menunggu verifikasi pengelola
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-6 text-center text-zinc-400">Belum ada pengajuan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="px-5 py-3 text-xs text-zinc-500">Saldo tidak cukup / ada pengajuan berjalan → pengajuan baru ditolak. Semua perubahan status tercatat pada jejak audit.</p>
        </div>
    </div>
</div>
