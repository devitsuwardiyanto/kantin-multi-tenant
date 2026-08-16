<x-layouts.admin title="Tenant">
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-green-100 px-4 py-2 text-sm text-green-800 dark:bg-green-900/40 dark:text-green-300">{{ session('status') }}</div>
    @endif
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-semibold">Tenant — {{ $canteen->name }}</h1>
        <a href="{{ route('admin.tenants.create') }}"><x-button>+ Buat Tenant</x-button></a>
    </div>
    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
        <table class="w-full min-w-[40rem] text-left text-sm">
            <thead class="bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                <tr><th class="px-4 py-2">Nama</th><th class="px-4 py-2">Kode</th><th class="px-4 py-2">Status</th><th class="px-4 py-2"></th></tr>
            </thead>
            <tbody>
                @forelse ($tenants as $tenant)
                    <tr class="border-t border-zinc-200 dark:border-zinc-800">
                        <td class="px-4 py-3">{{ $tenant->display_name }}</td>
                        <td class="px-4 py-3 font-mono">{{ $tenant->code }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$tenant->status">{{ $tenant->status }}</x-status-badge></td>
                        <td class="px-4 py-3 text-right"><a class="text-sm font-medium underline" href="{{ route('admin.tenants.edit', $tenant) }}">Kelola</a></td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-3" colspan="4"><x-empty-state title="Belum ada tenant" description="Buat tenant pertama untuk kantin ini." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.admin>
