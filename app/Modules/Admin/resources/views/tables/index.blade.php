<x-layouts.admin title="Meja & QR">
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-green-100 px-4 py-2 text-sm text-green-800 dark:bg-green-900/40 dark:text-green-300">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-100 px-4 py-2 text-sm text-red-800 dark:bg-red-900/40 dark:text-red-300">
            <ul class="list-disc ps-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
    <h1 class="text-xl font-semibold">Meja — {{ $canteen->name }}</h1>

    <form method="POST" action="{{ route('admin.tables.store') }}" class="mt-4 flex flex-wrap items-end gap-2">
        @csrf
        <x-input name="code" label="Kode (unik/kantin)" required />
        <x-input name="label" label="Label" required />
        <x-input name="zone" label="Zona (opsional)" />
        <x-button type="submit">+ Tambah Meja</x-button>
    </form>

    <div class="mt-6 overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
        <table class="w-full min-w-[44rem] text-left text-sm">
            <thead class="bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                <tr><th class="px-4 py-2">Kode</th><th class="px-4 py-2">Label</th><th class="px-4 py-2">Zona</th><th class="px-4 py-2">QR aktif</th><th class="px-4 py-2"></th></tr>
            </thead>
            <tbody>
                @forelse ($tables as $table)
                    <tr class="border-t border-zinc-200 dark:border-zinc-800">
                        <td class="px-4 py-3 font-mono">{{ $table->code }}</td>
                        <td class="px-4 py-3">{{ $table->label }}</td>
                        <td class="px-4 py-3">{{ $table->zone ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <x-status-badge :status="$table->activeToken ? 'active' : 'pending'">{{ $table->activeToken ? 'aktif' : 'belum ada' }}</x-status-badge>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="{{ route('admin.tables.rotate', $table) }}" class="inline">
                                @csrf
                                <x-button variant="secondary" type="submit">{{ $table->activeToken ? 'Rotate QR' : 'Terbitkan QR' }}</x-button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-3" colspan="5"><x-empty-state title="Belum ada meja" description="Tambahkan meja untuk menerbitkan QR." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.admin>
