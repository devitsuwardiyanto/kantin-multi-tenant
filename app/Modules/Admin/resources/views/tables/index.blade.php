<x-layouts.admin title="Meja & QR Code">
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-green-100 px-4 py-2 text-sm text-green-800 dark:bg-green-900/40 dark:text-green-300">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-100 px-4 py-2 text-sm text-red-800 dark:bg-red-900/40 dark:text-red-300">
            <ul class="list-disc ps-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
    <div class="flex flex-wrap items-baseline gap-2">
        <h1 class="text-xl font-semibold">Meja &amp; QR Code — {{ $canteen->name }}</h1>
        <span class="text-sm text-zinc-500" data-test="table-summary">· {{ $tables->count() }} meja, {{ $tables->where('status', 'active')->count() }} aktif</span>
    </div>

    <form method="POST" action="{{ route('admin.tables.store') }}" class="mt-4 flex flex-wrap items-end gap-2">
        @csrf
        <x-input name="code" label="Kode (unik/kantin)" required />
        <x-input name="label" label="Label" required />
        <x-input name="zone" label="Zona (opsional)" />
        <x-button type="submit">+ Tambah meja</x-button>
    </form>

    <div class="mt-6 overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
        <table class="w-full min-w-[48rem] text-left text-sm">
            <thead class="bg-zinc-100 text-xs uppercase tracking-wide text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                <tr><th class="px-4 py-2">Meja</th><th class="px-4 py-2">Zona</th><th class="px-4 py-2">Status</th><th class="px-4 py-2">QR aktif</th><th class="px-4 py-2 text-right">Aksi</th></tr>
            </thead>
            <tbody>
                @forelse ($tables as $table)
                    <tr @class(['border-t border-zinc-200 dark:border-zinc-800', 'text-zinc-400' => $table->status !== 'active'])>
                        <td class="px-4 py-3 font-medium">{{ $table->label }} <span class="ms-1 font-mono text-xs text-zinc-500">{{ $table->code }}</span></td>
                        <td class="px-4 py-3">{{ $table->zone ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <x-status-badge :status="$table->status === 'active' ? 'active' : 'default'">{{ $table->status === 'active' ? 'AKTIF' : 'NONAKTIF' }}</x-status-badge>
                        </td>
                        <td class="px-4 py-3">
                            @if ($table->activeToken)
                                <span class="text-xs">sejak {{ $table->activeToken->issued_at?->timezone(config('app.display_timezone'))->translatedFormat('j M Y H:i') }}</span>
                            @else
                                <x-status-badge status="pending">belum ada</x-status-badge>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                @if ($table->status === 'active')
                                    <form method="POST" action="{{ route('admin.tables.rotate', $table) }}"
                                          @if ($table->activeToken) onsubmit="return confirm('Regenerasi QR {{ $table->label }}? QR cetakan lama langsung tidak berlaku.')" @endif>
                                        @csrf
                                        <x-button variant="secondary" type="submit">{{ $table->activeToken ? 'Regenerasi & unduh QR' : 'Terbitkan & unduh QR' }}</x-button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.tables.status', $table) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="{{ $table->status === 'active' ? 'inactive' : 'active' }}">
                                    <button type="submit" class="min-h-11 px-2 text-sm font-medium text-red-700 hover:underline dark:text-red-400">{{ $table->status === 'active' ? 'Nonaktifkan' : 'Aktifkan kembali' }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-3" colspan="5"><x-empty-state title="Belum ada meja" description="Tambahkan meja untuk menerbitkan QR." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-xs text-zinc-500">Token opaque unik ≥128 bit; URL tidak memuat ID meja maupun tenant. Regenerasi langsung menonaktifkan QR cetakan lama.</p>
</x-layouts.admin>
