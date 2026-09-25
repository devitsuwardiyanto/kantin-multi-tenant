<x-layouts.admin title="Dashboard">
    <h1 class="text-xl font-semibold">Dashboard Pengelola</h1>
    {{-- Navigasi pengelola kantin: setiap tautan menuju halaman milik modulnya. --}}
    <nav class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4" aria-label="Menu pengelola" data-test="admin-nav">
        @foreach ([
            ['admin.tenants.index', 'Tenant & komisi', 'UC-21 · onboarding, rekening, skema komisi'],
            ['admin.tables.index', 'Meja & QR Code', 'UC-22 · terbitkan dan cetak QR meja'],
            ['admin.withdrawals.index', 'Pencairan dana', 'UC-23 · verifikasi penarikan tenant'],
            ['admin.follow-ups', 'Perlu tindak lanjut', 'UC-08 · UC-15 · peninjauan & pengembalian dana'],
        ] as [$route, $label, $hint])
            <a href="{{ route($route) }}" class="block rounded-xl border border-zinc-200 bg-white p-4 hover:border-zinc-900 dark:border-zinc-800 dark:bg-zinc-900">
                <span class="block font-semibold">{{ $label }}</span>
                <span class="mt-1 block text-xs text-zinc-500">{{ $hint }}</span>
            </a>
        @endforeach
    </nav>
</x-layouts.admin>
