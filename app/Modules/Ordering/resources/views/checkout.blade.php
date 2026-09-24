<x-layouts.customer :title="'Checkout'">
    {{-- UC-05 Checkout Pesanan (+ UC-06 Pre-Order): ringkasan, mode penyajian, identitas ringkas. --}}
    <livewire:ordering::checkout :canteen-slug="$canteen" />
</x-layouts.customer>
