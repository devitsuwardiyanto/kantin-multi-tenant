<x-layouts.customer :title="$canteen->name">
    <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/40" data-test="canteen-closed">
        <h1 class="text-lg font-bold">Kantin sedang tutup</h1>
        <p class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">QR {{ $table->label }} valid, tetapi pemesanan dibuka kembali saat tenant beroperasi. Jam buka hari ini:</p>
    </div>
    <ul class="mt-4 divide-y divide-zinc-200 rounded-xl border border-zinc-200 bg-white dark:divide-zinc-800 dark:border-zinc-800 dark:bg-zinc-900">
        @foreach ($tenants as $tenant)
            <li class="flex min-h-12 items-center justify-between px-4 py-3 text-sm">
                <span class="font-medium">{{ $tenant->display_name }}</span>
                <span class="text-zinc-600 dark:text-zinc-400">{{ $hours->todayLabel($tenant) ?? 'Libur hari ini' }}</span>
            </li>
        @endforeach
    </ul>
</x-layouts.customer>
