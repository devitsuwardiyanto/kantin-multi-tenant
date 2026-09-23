<x-layouts.customer :title="'Katalog'">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <livewire:catalog::menu-catalog :canteen-slug="$canteen" />
        </div>
        <div class="lg:sticky lg:top-4 lg:self-start">
            <livewire:ordering::cart :canteen-slug="$canteen" />
        </div>
    </div>
</x-layouts.customer>
