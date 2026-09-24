<x-layouts.tenant title="Kelola Menu — {{ $tenant->display_name }}">
    <div class="mb-4 flex items-baseline justify-between">
        <h1 class="text-xl font-semibold">Kelola Menu</h1>
        <span class="flex gap-4">
            <a href="{{ route('tenant.pre-order-settings', $tenant) }}" class="text-sm underline" wire:navigate>Pre-Order →</a>
            <a href="{{ route('tenant.modifier-manager', $tenant) }}" class="text-sm underline" wire:navigate>Kelola Modifier →</a>
        </span>
    </div>
    <livewire:catalog::menu-manager :tenant-id="$tenant->id" />
</x-layouts.tenant>
