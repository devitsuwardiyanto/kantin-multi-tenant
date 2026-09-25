<x-layouts.tenant title="Keuangan — {{ $tenant->display_name }}">
    <x-tenant.finance-tabs :tenant="$tenant" active="finance" />
    <livewire:reporting::finance-panel :tenant-id="$tenant->id" />
</x-layouts.tenant>
