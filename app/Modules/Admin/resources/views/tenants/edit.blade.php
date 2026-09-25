<x-layouts.admin title="{{ $tenant->display_name }}">
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-green-100 px-4 py-2 text-sm text-green-800 dark:bg-green-900/40 dark:text-green-300">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-100 px-4 py-2 text-sm text-red-800 dark:bg-red-900/40 dark:text-red-300">
            <ul class="list-disc ps-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
    <div class="flex items-center gap-3">
        <h1 class="text-xl font-semibold">{{ $tenant->display_name }}</h1>
        <x-status-badge :status="$tenant->status">{{ $tenant->status }}</x-status-badge>
    </div>

    <section class="mt-6 max-w-xl rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
        <h2 class="font-semibold">Ubah komisi — {{ $tenant->display_name }}</h2>
        <p class="mt-1 text-sm">Komisi aktif saat ini:
            <strong>{{ $activeCommission ? number_format((float) $activeCommission->commission_rate * 100, 2, ',', '.').'%' : '—' }}</strong></p>
        <form method="POST" action="{{ route('admin.tenants.commission.store', $tenant) }}" class="mt-3 space-y-3">
            @csrf
            <x-input name="commission_rate" label="Persentase komisi baru (%)" type="number" step="0.01" required />
            <x-input name="effective_at" label="Tanggal berlaku (hanya masa mendatang)" type="datetime-local" min="{{ now()->format('Y-m-d\\TH:i') }}" required />
            <p class="border-s-2 border-red-600 bg-red-50 px-3 py-2 text-xs text-zinc-700 dark:bg-red-950/30 dark:text-zinc-300">
                Versi baru berlaku mulai tanggal tersebut. Setiap tenant_order menyimpan snapshot komisi sehingga order lama tidak berubah.</p>
            <x-button type="submit" class="w-full">Simpan perubahan</x-button>
        </form>
        <h3 class="mt-5 text-xs font-semibold uppercase tracking-wide text-zinc-500">Riwayat perubahan</h3>
        <ul class="mt-2 space-y-1 text-sm" data-test="commission-history">
            @foreach ($commissions as $c)
                <li>{{ rtrim(rtrim(number_format((float) $c->commission_rate * 100, 2, ',', '.'), '0'), ',') }}% → berlaku {{ $c->valid_from->translatedFormat('j M Y H:i') }}
                    @if ($c->valid_to) s/d {{ $c->valid_to->translatedFormat('j M Y H:i') }} @endif
                    · oleh {{ $commissionActors[$c->id] ?? 'sistem' }}</li>
            @endforeach
        </ul>
    </section>

    <section class="mt-8">
        <h2 class="font-semibold">Rekening Bank</h2>
        <ul class="mt-2 space-y-1 text-sm">
            @foreach ($bankAccounts as $acc)
                <li>{{ $acc->bank_code }} •••• {{ $acc->account_last4 }} — {{ $acc->account_holder }}
                    <x-status-badge :status="$acc->status === 'verified' ? 'active' : 'pending'">{{ $acc->status }}</x-status-badge>
                    @if ($acc->is_primary) <span class="text-xs font-semibold">PRIMARY</span> @endif
                    {{-- UC-20 prasyarat: pengelola memverifikasi rekening tujuan pencairan. --}}
                    <span class="ms-2 inline-flex gap-2 align-middle">
                        @if ($acc->status !== 'verified')
                            <form method="POST" action="{{ route('admin.tenants.bank.verify', [$tenant, $acc]) }}">
                                @csrf
                                <input type="hidden" name="approve" value="1">
                                <button type="submit" class="text-xs font-semibold text-green-700 underline" data-test="bank-verify-{{ $acc->id }}">Verifikasi</button>
                            </form>
                        @endif
                        @if ($acc->status === 'unverified')
                            <form method="POST" action="{{ route('admin.tenants.bank.verify', [$tenant, $acc]) }}">
                                @csrf
                                <input type="hidden" name="approve" value="0">
                                <button type="submit" class="text-xs font-semibold text-red-700 underline">Tolak</button>
                            </form>
                        @endif
                        @if (! $acc->is_primary && $acc->status === 'verified')
                            <form method="POST" action="{{ route('admin.tenants.bank.primary', [$tenant, $acc]) }}">
                                @csrf
                                <button type="submit" class="text-xs font-semibold underline">Jadikan utama</button>
                            </form>
                        @endif
                    </span></li>
            @endforeach
        </ul>
        <form method="POST" action="{{ route('admin.tenants.bank.store', $tenant) }}" class="mt-3 flex flex-wrap items-end gap-2">
            @csrf
            <x-input name="bank_code" label="Bank" required />
            <x-input name="account_holder" label="Nama pemilik" required />
            <x-input name="account_number" label="Nomor rekening" required />
            <x-button type="submit">Tambah</x-button>
        </form>
    </section>

    <section class="mt-8">
        <h2 class="font-semibold">Operator / Role</h2>
        <ul class="mt-2 space-y-1 text-sm">
            @foreach ($members as $m)
                <li>{{ $m->user?->email }} — {{ $m->role }}</li>
            @endforeach
        </ul>
        <form method="POST" action="{{ route('admin.tenants.roles.store', $tenant) }}" class="mt-3 flex flex-wrap items-end gap-2">
            @csrf
            <x-input name="email" label="Email user" type="email" required />
            <x-input name="role" label="Role (owner/operator/cashier)" required />
            <x-button type="submit">Tugaskan</x-button>
        </form>
    </section>
</x-layouts.admin>
