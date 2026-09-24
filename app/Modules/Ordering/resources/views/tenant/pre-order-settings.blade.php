<x-layouts.tenant title="Pre-Order — {{ $tenant->display_name }}">
    <div class="mb-4 flex items-baseline justify-between">
        <h1 class="text-xl font-semibold">Pengaturan Pre-Order</h1>
        <a href="{{ route('tenant.menu-manager', $tenant) }}" class="text-sm underline" wire:navigate>← Kelola Menu</a>
    </div>
    <livewire:ordering::pre-order-settings :tenant-id="$tenant->id" />
</x-layouts.tenant>
