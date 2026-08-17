<?php

use App\Models\UserCanteenRole;
use App\Models\Withdrawal;
use App\Modules\Payments\Exceptions\WithdrawalException;
use App\Modules\Payments\Services\WithdrawalService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Peninjauan penarikan oleh pengelola/finance kantin. Kantin dikelola diverifikasi dari
 * UserCanteenRole (bukan input klien). Hanya penarikan tenant di bawah kantin tsb yang boleh
 * disetujui/ditolak — mencegah aksi lintas kantin.
 */
new class extends Component
{
    public int $canteenId = 0;

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

    #[Computed]
    public function pending(): \Illuminate\Database\Eloquent\Collection
    {
        return Withdrawal::query()
            ->withoutGlobalScope('tenant')
            ->where('status', 'requested')
            ->whereHas('tenant', fn ($q) => $q->where('canteen_id', $this->canteenId))
            ->with('tenant:id,display_name')
            ->orderBy('id')
            ->get();
    }

    private function resolveOwned(int $withdrawalId): ?Withdrawal
    {
        return Withdrawal::query()
            ->withoutGlobalScope('tenant')
            ->whereKey($withdrawalId)
            ->whereHas('tenant', fn ($q) => $q->where('canteen_id', $this->canteenId))
            ->first();
    }

    public function approve(int $withdrawalId): void
    {
        $this->act($withdrawalId, true);
    }

    public function reject(int $withdrawalId): void
    {
        $this->act($withdrawalId, false);
    }

    private function act(int $withdrawalId, bool $approve): void
    {
        $withdrawal = $this->resolveOwned($withdrawalId);
        abort_if($withdrawal === null, 403);

        try {
            $service = app(WithdrawalService::class);
            $approve ? $service->approve($withdrawal, Auth::user()) : $service->reject($withdrawal, Auth::user());
        } catch (WithdrawalException $e) {
            $this->addError('review', $e->getMessage());
        }

        unset($this->pending);
    }
};
?>

<div class="space-y-4">
    @php($rupiah = fn (int $n) => 'Rp '.number_format($n, 0, ',', '.'))

    @error('review')
        <div class="rounded-lg bg-red-100 px-3 py-2 text-sm text-red-800 dark:bg-red-900/40 dark:text-red-300">{{ $message }}</div>
    @enderror

    <div class="overflow-x-auto rounded-2xl border border-zinc-200 dark:border-zinc-800">
        <table class="w-full min-w-[36rem] text-left text-sm">
            <thead class="bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                <tr><th class="px-4 py-2">Tenant</th><th class="px-4 py-2">Nominal</th><th class="px-4 py-2 text-right">Aksi</th></tr>
            </thead>
            <tbody>
                @forelse ($this->pending as $withdrawal)
                    <tr wire:key="wd-{{ $withdrawal->id }}" class="border-t border-zinc-200 dark:border-zinc-800">
                        <td class="px-4 py-3">{{ $withdrawal->tenant->display_name }}</td>
                        <td class="px-4 py-3 font-semibold">{{ $rupiah((int) $withdrawal->amount) }}</td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" wire:click="approve({{ $withdrawal->id }})" class="me-2 text-sm font-medium text-green-700 underline dark:text-green-400">Setujui</button>
                            <button type="button" wire:click="reject({{ $withdrawal->id }})" class="text-sm font-medium text-red-600 underline">Tolak</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-4 text-zinc-400">Tidak ada penarikan menunggu tinjauan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
