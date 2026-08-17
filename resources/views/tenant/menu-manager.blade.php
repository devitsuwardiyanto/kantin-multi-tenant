<x-layouts.tenant title="Kelola Menu — {{ $tenant->display_name }}">
    <h1 class="mb-4 text-xl font-semibold">Kelola Menu</h1>
    <livewire:tenant.menu-manager :tenant-id="$tenant->id" />
</x-layouts.tenant>
