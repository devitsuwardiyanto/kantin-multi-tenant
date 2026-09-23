<x-layouts.tenant title="Kelola Menu — {{ $tenant->display_name }}">
    <h1 class="mb-4 text-xl font-semibold">Kelola Menu</h1>
    <livewire:catalog::menu-manager :tenant-id="$tenant->id" />
</x-layouts.tenant>
