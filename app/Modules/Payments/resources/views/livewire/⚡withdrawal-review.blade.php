<?php

use App\Models\TenantBalance;
use App\Models\UserCanteenRole;
use App\Models\Withdrawal;
use App\Modules\Payments\Exceptions\WithdrawalException;
use App\Modules\Payments\Services\WithdrawalService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * UC-23 Verifikasi Pencairan Dana oleh pengelola/finance kantin. Kantin dikelola diverifikasi dari
 * UserCanteenRole (bukan input klien). Hanya penarikan tenant di bawah kantin tsb yang boleh
 * dilihat/disetujui/ditolak — mencegah aksi lintas kantin. Persetujuan wajib bukti transfer,
 * penolakan wajib alasan; ledger tidak sesuai → investigasi (WithdrawalService).
 */
new class extends Component
{
    use WithFileUploads;

    public int $canteenId = 0;

    public ?int $selectedId = null;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $proof = null;

    public string $note = '';

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

    /** Langkah 1: permintaan aktif dahulu, lalu yang sudah diputuskan. */
    #[Computed]
    public function withdrawals(): \Illuminate\Database\Eloquent\Collection
    {
        return Withdrawal::query()
            ->withoutGlobalScope('tenant')
            ->whereHas('tenant', fn ($q) => $q->where('canteen_id', $this->canteenId))
            ->with('tenant:id,display_name')
            ->orderByRaw("CASE WHEN status IN ('requested', 'investigation') THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->limit(30)
            ->get();
    }

    #[Computed]
    public function pending(): \Illuminate\Support\Collection
    {
        return $this->withdrawals->whereIn('status', Withdrawal::ACTIVE_STATUSES)->values();
    }

    #[Computed]
    public function selected(): ?Withdrawal
    {
        $id = $this->selectedId ?? $this->pending->first()?->id;

        return $id === null ? null : $this->resolveOwned($id)?->load('tenant:id,display_name', 'bankAccount');
    }

    /**
     * Langkah 2–3: saldo, riwayat transaksi tenant, dan kesesuaian ledger.
     *
     * @return array{available: int, held: int, matches: bool, entries: int, recent: \Illuminate\Support\Collection<int, object>}|null
     */
    #[Computed]
    public function ledger(): ?array
    {
        $withdrawal = $this->selected;
        if ($withdrawal === null) {
            return null;
        }

        $tenantId = (int) $withdrawal->tenant_id;
        $balance = TenantBalance::query()->firstOrNew(['tenant_id' => $tenantId]);

        return [
            'available' => (int) $balance->available_amount,
            'held' => (int) $balance->held_amount,
            'matches' => app(WithdrawalService::class)->ledgerMatches($tenantId),
            'entries' => DB::table('ledger_entries')->where('tenant_id', $tenantId)->count(),
            'recent' => DB::table('ledger_entries')->where('tenant_id', $tenantId)->orderByDesc('id')->limit(5)->get(['type', 'available_delta', 'held_delta', 'created_at']),
        ];
    }

    private function resolveOwned(int $withdrawalId): ?Withdrawal
    {
        return Withdrawal::query()
            ->withoutGlobalScope('tenant')
            ->whereKey($withdrawalId)
            ->whereHas('tenant', fn ($q) => $q->where('canteen_id', $this->canteenId))
            ->first();
    }

    public function select(int $withdrawalId): void
    {
        abort_if($this->resolveOwned($withdrawalId) === null, 403);
        $this->selectedId = $withdrawalId;
        $this->reset('proof', 'note');
        $this->resetErrorBag();
    }

    /** Langkah 4–5: setujui dengan bukti transfer (JPG/PNG/PDF, maks 5 MB). */
    public function approve(int $withdrawalId): void
    {
        $withdrawal = $this->resolveOwned($withdrawalId);
        abort_if($withdrawal === null, 403);

        $this->validate(['proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120']], [
            'proof.required' => 'Unggah bukti transfer sebelum menyetujui pencairan.',
            'proof.mimes' => 'Bukti transfer harus JPG, PNG, atau PDF.',
            'proof.max' => 'Ukuran bukti transfer maksimal 5 MB.',
        ]);

        $path = $this->proof->store('withdrawal-proofs/tenant-'.$withdrawal->tenant_id, 'local');
        $this->selectedId = $withdrawal->id;
        $this->decide(fn (WithdrawalService $service) => $service->approve($withdrawal, Auth::user(), $path ?: null), 'Penarikan '.$withdrawal->reference().' ditandai dicairkan.');
    }

    /** Alur 4a: tolak dengan alasan; saldo tertahan dilepas. */
    public function reject(int $withdrawalId): void
    {
        $withdrawal = $this->resolveOwned($withdrawalId);
        abort_if($withdrawal === null, 403);

        $this->selectedId = $withdrawal->id;
        $this->decide(fn (WithdrawalService $service) => $service->reject($withdrawal, Auth::user(), $this->note), 'Penarikan '.$withdrawal->reference().' ditolak; dana dikembalikan ke saldo tenant.');
    }

    private function decide(\Closure $action, string $success): void
    {
        try {
            $action(app(WithdrawalService::class));
            $this->reset('proof', 'note');
            session()->flash('status', $success);
        } catch (WithdrawalException $e) {
            $this->addError('review', $e->getMessage());
        }

        unset($this->withdrawals, $this->pending, $this->selected, $this->ledger);
    }
};
?>

<div class="space-y-4">
    @php($rupiah = fn (int $n) => 'Rp'.number_format($n, 0, ',', '.'))
    @php($selected = $this->selected)

    @if (session('status'))
        <div class="border-l-4 border-green-700 bg-green-50 px-4 py-2 text-sm font-semibold text-green-800 dark:bg-green-950/40 dark:text-green-300">{{ session('status') }}</div>
    @endif
    @error('review')
        <div class="border-l-4 border-red-600 bg-red-50 px-4 py-2 text-sm font-semibold text-red-800 dark:bg-red-950/40 dark:text-red-300">{{ $message }}</div>
    @enderror

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-5">
        <div class="border-2 border-zinc-900 bg-white lg:col-span-2 dark:border-zinc-100 dark:bg-zinc-900">
            <p class="border-b-2 border-zinc-900 px-4 py-3 text-lg font-bold dark:border-zinc-100">Permintaan penarikan
                @if ($this->pending->isNotEmpty())<span class="ms-1 bg-red-600 px-2 text-sm text-white">{{ $this->pending->count() }}</span>@endif
            </p>
            @forelse ($this->withdrawals as $withdrawal)
                @php($active = in_array($withdrawal->status, \App\Models\Withdrawal::ACTIVE_STATUSES, true))
                <button type="button" wire:key="wd-{{ $withdrawal->id }}" wire:click="select({{ $withdrawal->id }})"
                    class="block w-full border-b border-zinc-200 px-4 py-3 text-left dark:border-zinc-800 {{ $selected?->id === $withdrawal->id ? 'border-l-4 border-l-red-600' : '' }} {{ $active ? '' : 'bg-zinc-50 text-zinc-500 dark:bg-zinc-950' }}">
                    <span class="flex items-center justify-between gap-2">
                        <span class="font-bold">{{ $withdrawal->tenant->display_name }}</span>
                        <span class="px-2 py-0.5 text-xs font-extrabold uppercase text-white {{ match ($withdrawal->status) { 'paid' => 'bg-green-700', 'rejected' => 'bg-zinc-500', 'investigation' => 'bg-amber-600', default => 'bg-red-600' } }}">
                            {{ match ($withdrawal->status) { 'paid' => 'Dicairkan', 'rejected' => 'Ditolak', 'investigation' => 'Investigasi', default => 'Menunggu' } }}
                        </span>
                    </span>
                    <span class="block text-xl font-extrabold">{{ $rupiah((int) $withdrawal->amount) }}</span>
                    <span class="block text-xs">{{ $active ? 'Diajukan' : 'Selesai' }} {{ ($active ? $withdrawal->created_at : ($withdrawal->reviewed_at ?? $withdrawal->updated_at))->setTimezone(config('app.display_timezone'))->locale('id')->translatedFormat('j M, H.i') }} · {{ $withdrawal->reference() }}</span>
                </button>
            @empty
                <p class="px-4 py-6 text-center text-sm text-zinc-400">Tidak ada permintaan penarikan.</p>
            @endforelse
        </div>

        <div class="lg:col-span-3">
            @if ($selected && $this->ledger)
                @php($ledger = $this->ledger)
                @php($reviewable = $selected->status === 'requested')
                <div class="border-2 border-zinc-900 bg-white dark:border-zinc-100 dark:bg-zinc-900">
                    <div class="flex flex-wrap items-center gap-2 border-b-2 border-zinc-900 px-5 py-3 dark:border-zinc-100">
                        <p class="text-lg font-bold">{{ $selected->reference() }} · {{ $selected->tenant->display_name }}</p>
                        <span class="ms-auto px-2 py-1 text-xs font-extrabold uppercase text-white {{ match ($selected->status) { 'paid' => 'bg-green-700', 'rejected' => 'bg-zinc-500', 'investigation' => 'bg-amber-600', default => 'bg-red-600' } }}">
                            {{ match ($selected->status) { 'paid' => 'Dicairkan', 'rejected' => 'Ditolak', 'investigation' => 'Investigasi', default => 'Menunggu persetujuan' } }}
                        </span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2">
                        <div class="space-y-2 border-zinc-200 p-5 text-sm md:border-e dark:border-zinc-800">
                            <p class="text-xs font-bold uppercase tracking-wider text-zinc-500">Rincian permintaan</p>
                            <p class="flex justify-between gap-2 border-b border-zinc-200 pb-2 dark:border-zinc-800"><span>Nominal diminta</span><strong>{{ $rupiah((int) $selected->amount) }}</strong></p>
                            <p class="flex justify-between gap-2 border-b border-zinc-200 pb-2 dark:border-zinc-800"><span>Dana tertahan (ledger)</span>
                                <strong class="{{ $ledger['held'] >= $selected->amount || ! $reviewable ? 'text-green-700' : 'text-red-600' }}">{{ $rupiah($ledger['held']) }}{{ $reviewable ? ($ledger['held'] >= $selected->amount ? ' ✓ cukup' : ' ✗ kurang') : '' }}</strong></p>
                            <p class="flex justify-between gap-2 border-b border-zinc-200 pb-2 dark:border-zinc-800"><span>Saldo tersedia</span><strong>{{ $rupiah($ledger['available']) }}</strong></p>
                            <p class="flex justify-between gap-2 border-b border-zinc-200 pb-2 dark:border-zinc-800"><span>Rekening tujuan</span><strong>{{ $selected->bankAccount?->bank_code }} ••••{{ $selected->bankAccount?->account_last4 }} · {{ $selected->bankAccount?->account_holder }}</strong></p>
                            <p class="flex justify-between gap-2"><span>Kesesuaian ledger</span>
                                <strong class="{{ $ledger['matches'] ? 'text-green-700' : 'text-red-600' }}">{{ $ledger['matches'] ? 'Cocok — '.$ledger['entries'].' entri ✓' : 'Tidak cocok ✗' }}</strong></p>
                            <p class="pt-2 text-xs font-bold uppercase tracking-wider text-zinc-500">Riwayat transaksi tenant</p>
                            <ul class="space-y-1 text-xs text-zinc-600 dark:text-zinc-300">
                                @foreach ($ledger['recent'] as $entry)
                                    <li class="flex justify-between gap-2"><span>{{ $entry->type }}</span><span>{{ $entry->available_delta >= 0 ? '+' : '–' }}{{ $rupiah(abs((int) $entry->available_delta)) }}@if ((int) $entry->held_delta !== 0) · tertahan {{ (int) $entry->held_delta > 0 ? '+' : '–' }}{{ $rupiah(abs((int) $entry->held_delta)) }}@endif</span></li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="space-y-3 p-5 text-sm">
                            @if ($reviewable)
                                <p class="text-xs font-bold uppercase tracking-wider text-zinc-500">Bukti transfer</p>
                                <label class="block cursor-pointer border-2 border-dashed border-zinc-400 px-4 py-6 text-center font-semibold text-zinc-500">
                                    <input type="file" wire:model="proof" accept=".jpg,.jpeg,.png,.pdf" class="sr-only" />
                                    @if ($proof)
                                        <span class="text-green-700">✓ {{ $proof->getClientOriginalName() }}</span>
                                    @else
                                        Seret berkas ke sini<br>atau <span class="text-red-600">pilih berkas</span> (JPG/PDF, maks 5 MB)
                                    @endif
                                </label>
                                <div wire:loading wire:target="proof" class="text-xs text-zinc-500">Mengunggah…</div>
                                @error('proof') <p class="text-sm font-semibold text-red-600">{{ $message }}</p> @enderror
                            @elseif ($selected->status === 'paid')
                                <p class="text-xs font-bold uppercase tracking-wider text-zinc-500">Bukti transfer</p>
                                <a href="{{ route('admin.withdrawals.proof', $selected->id) }}" class="font-bold text-red-600 underline" target="_blank">Lihat bukti transfer</a>
                            @endif
                            @if (in_array($selected->status, \App\Models\Withdrawal::ACTIVE_STATUSES, true))
                                <label class="block text-sm font-semibold">Catatan (wajib bila menolak)
                                    <textarea wire:model="note" rows="3" maxlength="500" placeholder="Tulis catatan…" class="mt-1 block w-full border border-zinc-400 px-3 py-2 dark:bg-zinc-950"></textarea>
                                </label>
                            @elseif ($selected->review_note)
                                <p><span class="font-semibold">Alasan penolakan:</span> {{ $selected->review_note }}</p>
                            @endif
                            @if ($selected->status === 'investigation')
                                <p class="font-semibold text-amber-700">Ledger tidak sesuai — permintaan dalam investigasi dan tidak dapat disetujui. Tolak untuk melepas dana tertahan setelah pemeriksaan.</p>
                            @endif
                        </div>
                    </div>
                    @if (in_array($selected->status, \App\Models\Withdrawal::ACTIVE_STATUSES, true))
                        <div class="flex flex-wrap gap-3 border-t-2 border-zinc-900 p-5 dark:border-zinc-100">
                            @if ($reviewable)
                                <button type="button" wire:click="approve({{ $selected->id }})" wire:loading.attr="disabled" class="flex-1 bg-red-600 px-4 py-3 font-bold text-white disabled:opacity-50">Setujui &amp; tandai dicairkan ✓</button>
                            @endif
                            <button type="button" wire:click="reject({{ $selected->id }})" wire:loading.attr="disabled" class="border-2 border-zinc-900 px-4 py-3 font-bold dark:border-zinc-100">Tolak permintaan</button>
                        </div>
                    @endif
                </div>
                <p class="mt-3 text-xs text-zinc-500">Persetujuan mengunci withdrawal, melepaskan hold, dan menulis ledger debit + audit append-only dalam satu transaksi. Proses idempoten; persetujuan bersamaan tidak dapat mencairkan dua kali.</p>
            @else
                <p class="border-2 border-dashed border-zinc-400 px-4 py-10 text-center text-zinc-400">Pilih permintaan penarikan untuk ditinjau.</p>
            @endif
        </div>
    </div>
</div>
