<x-layouts.tenant title="Dashboard">
    <div class="flex items-center gap-3">
        <h1 class="text-xl font-semibold">{{ $tenant->display_name }}</h1>
        <x-status-badge :status="$tenant->status">{{ $tenant->status }}</x-status-badge>
    </div>
    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Slug: <span class="font-mono">{{ $tenant->slug }}</span></p>
    <div class="mt-6">
        <x-empty-state title="Belum ada aktivitas"
            description="Katalog, pesanan, dan KDS tenant akan tampil di sini (Modul 7 & 12)." />
    </div>
</x-layouts.tenant>
