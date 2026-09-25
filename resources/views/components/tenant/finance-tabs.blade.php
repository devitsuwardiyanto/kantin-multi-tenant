@props(['tenant', 'active' => 'finance'])

{{-- Navigasi halaman keuangan tenant (modul Reporting + Payments). --}}
<nav class="mb-5 flex flex-wrap gap-1 border-b border-zinc-300 text-sm font-bold dark:border-zinc-700" aria-label="Keuangan tenant">
    @foreach ([
        'finance' => ['tenant.finance', 'Ringkasan'],
        'reports' => ['tenant.reports', 'Laporan penjualan'],
        'reconciliation' => ['tenant.reconciliation', 'Rekonsiliasi'],
        'withdrawals' => ['tenant.withdrawals', 'Penarikan dana'],
    ] as $key => [$route, $label])
        <a href="{{ route($route, ['tenant' => $tenant->slug]) }}" @if ($key === $active) aria-current="page" @endif
            class="-mb-px border-b-2 px-3 py-2 {{ $key === $active ? 'border-red-600 text-zinc-900 dark:text-zinc-100' : 'border-transparent text-zinc-500 hover:text-zinc-900' }}">{{ $label }}</a>
    @endforeach
</nav>
