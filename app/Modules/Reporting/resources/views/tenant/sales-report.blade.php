<x-layouts.tenant title="Laporan — {{ $tenant->display_name }}">
    <x-tenant.finance-tabs :tenant="$tenant" active="reports" />
    <livewire:reporting::sales-report :tenant-id="$tenant->id" />
</x-layouts.tenant>
