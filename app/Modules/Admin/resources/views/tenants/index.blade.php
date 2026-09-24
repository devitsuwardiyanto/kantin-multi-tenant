<x-layouts.admin title="Tenant & Skema Komisi">
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-green-100 px-4 py-2 text-sm text-green-800 dark:bg-green-900/40 dark:text-green-300">{{ session('status') }}</div>
    @endif
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-semibold">Tenant &amp; Skema Komisi — {{ $canteen->name }}</h1>
        <a href="{{ route('admin.tenants.create') }}"><x-button>+ Daftarkan tenant</x-button></a>
    </div>
    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
        <table class="w-full min-w-[48rem] text-left text-sm">
            <thead class="bg-zinc-100 text-xs uppercase tracking-wide text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                <tr>
                    <th class="px-4 py-2">Tenant</th>
                    <th class="px-4 py-2">Penanggung jawab</th>
                    <th class="px-4 py-2">Komisi</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tenants as $tenant)
                    @php
                        $owner = $tenant->tenantRoles->first()?->user;
                        $bank = $tenant->bankAccounts->first();
                        $commission = $tenant->commissionSchemes->first();
                    @endphp
                    <tr @class(['border-t border-zinc-200 dark:border-zinc-800', 'text-zinc-400' => $tenant->status !== 'active'])>
                        <td class="px-4 py-3 font-medium">{{ $tenant->display_name }} <span class="ms-1 font-mono text-xs text-zinc-500">{{ $tenant->code }}</span></td>
                        <td class="px-4 py-3">
                            {{ $owner?->name ?? '—' }}
                            @if ($bank) · {{ $bank->bank_code }} ••{{ $bank->account_last4 }} @endif
                        </td>
                        <td class="px-4 py-3 font-semibold">{{ $commission ? rtrim(rtrim(number_format((float) $commission->commission_rate * 100, 2, ',', '.'), '0'), ',').'%' : '—' }}</td>
                        <td class="px-4 py-3">
                            <x-status-badge :status="$tenant->status === 'active' ? 'active' : 'default'">{{ $tenant->status === 'active' ? 'AKTIF' : 'NONAKTIF' }}</x-status-badge>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a class="text-sm font-medium underline" href="{{ route('admin.tenants.edit', $tenant) }}">Ubah komisi</a>
                                <form method="POST" action="{{ route('admin.tenants.status', $tenant) }}"
                                      onsubmit="return confirm('{{ $tenant->status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }} {{ $tenant->display_name }}?')">
                                    @csrf
                                    <input type="hidden" name="status" value="{{ $tenant->status === 'active' ? 'inactive' : 'active' }}">
                                    <button type="submit" class="min-h-11 text-sm font-medium text-red-700 hover:underline dark:text-red-400">
                                        {{ $tenant->status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-3" colspan="5"><x-empty-state title="Belum ada tenant" description="Daftarkan tenant pertama untuk kantin ini." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-xs text-zinc-500">Tenant nonaktif disembunyikan dari katalog pelanggan tanpa menghapus data historis.</p>
</x-layouts.admin>
