<x-layouts.tenant title="Dashboard">
    <div class="flex items-center gap-3">
        <h1 class="text-xl font-semibold">Dashboard Tenant</h1>
        <x-status-badge status="active">aktif</x-status-badge>
    </div>
    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Tenant: <span class="font-mono">{{ $tenant }}</span></p>
    <div class="mt-6">
        <x-empty-state title="Belum ada aktivitas"
            description="Katalog, pesanan, dan KDS tenant akan tampil di sini (Modul 7 & 12)." />
    </div>
</x-layouts.tenant>
