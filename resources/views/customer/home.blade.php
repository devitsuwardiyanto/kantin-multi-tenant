<x-layouts.customer :title="'Katalog'">
    {{-- Tata letak mobile-first (layout pelanggan max-w-md): katalog lalu keranjang, bertumpuk. --}}
    <div class="space-y-6">
        <livewire:catalog::menu-catalog :canteen-slug="$canteen" />
        <div id="keranjang">
            <livewire:ordering::cart :canteen-slug="$canteen" />
        </div>
    </div>
    <livewire:ordering::item-customizer />
</x-layouts.customer>
