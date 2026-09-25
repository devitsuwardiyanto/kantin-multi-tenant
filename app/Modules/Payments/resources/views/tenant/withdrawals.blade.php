<x-layouts.tenant title="Penarikan Dana — {{ $tenant->display_name }}">
    <x-tenant.finance-tabs :tenant="$tenant" active="withdrawals" />
    <livewire:payments::withdrawal-request :tenant-id="$tenant->id" />
</x-layouts.tenant>
