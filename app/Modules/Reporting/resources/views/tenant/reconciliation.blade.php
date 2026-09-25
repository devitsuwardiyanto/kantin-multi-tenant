<x-layouts.tenant title="Rekonsiliasi — {{ $tenant->display_name }}">
    <x-tenant.finance-tabs :tenant="$tenant" active="reconciliation" />
    <livewire:reporting::reconciliation :tenant-id="$tenant->id" />
</x-layouts.tenant>
