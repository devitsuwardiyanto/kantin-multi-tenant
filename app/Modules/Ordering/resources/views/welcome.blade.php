<x-layouts.customer :title="$canteen->name">
    @if ($table)
        <x-slot:headerRight><span class="text-xs font-bold uppercase tracking-wider text-red-600">{{ $table->label }}</span></x-slot:headerRight>
        <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-green-700 dark:text-green-400">
            <span class="size-2 rounded-full bg-green-600"></span> Sesi pemesanan aktif
        </div>
        <h1 class="mt-2 text-2xl font-extrabold leading-tight">Selamat datang,<br>{{ $table->label }}@if ($table->zone) — {{ $table->zone }}@endif</h1>
        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">QR meja tervalidasi. Pilih tenant untuk mulai memesan — satu keranjang, satu kali bayar.</p>
    @else
        <x-empty-state title="Pindai QR meja untuk memulai"
            description="Sesi pemesanan dibuat otomatis setelah Anda memindai QR Code pada meja." />
    @endif

    <ul class="mt-5 divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:divide-zinc-800 dark:border-zinc-800 dark:bg-zinc-900" data-test="tenant-list">
        @foreach ($tenants as $tenant)
            @php($open = $hours->isOpen($tenant))
            <li @class(['flex min-h-14 items-center justify-between px-4 py-3', 'opacity-60' => ! $open])>
                <div>
                    <div class="font-semibold">{{ $tenant->display_name }}</div>
                    <div class="text-xs text-zinc-500">
                        @if ($open)
                            Buka{{ $hours->todayLabel($tenant) ? ' · '.$hours->todayLabel($tenant) : '' }}
                        @else
                            Tutup{{ $hours->opensAtToday($tenant) ? ' · Buka pukul '.$hours->opensAtToday($tenant) : ' hari ini' }}
                        @endif
                    </div>
                </div>
                @unless ($open)
                    <span class="rounded border border-zinc-300 px-2 py-0.5 text-[10px] font-semibold text-zinc-500">TUTUP</span>
                @endunless
            </li>
        @endforeach
    </ul>

    @if ($table)
        <a href="{{ route('customer.home', ['canteen' => $canteen->slug]) }}" class="mt-6 flex min-h-12 w-full items-center justify-center rounded-lg bg-red-600 font-semibold text-white hover:bg-red-700">Lihat semua menu →</a>
    @endif
</x-layouts.customer>
