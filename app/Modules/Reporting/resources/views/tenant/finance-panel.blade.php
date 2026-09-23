<x-layouts.tenant title="Keuangan — {{ $tenant->display_name }}">
    <h1 class="mb-4 text-xl font-semibold">Keuangan & Penarikan</h1>
    <livewire:reporting::finance-panel :tenant-id="$tenant->id" />
</x-layouts.tenant>
