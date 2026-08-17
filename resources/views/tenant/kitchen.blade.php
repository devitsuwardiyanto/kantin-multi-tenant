<x-layouts.tenant title="Dapur — {{ $tenant->display_name }}">
    <h1 class="mb-4 text-xl font-semibold">Kitchen Display</h1>
    <livewire:tenant.kitchen-board :tenant-id="$tenant->id" />
</x-layouts.tenant>
