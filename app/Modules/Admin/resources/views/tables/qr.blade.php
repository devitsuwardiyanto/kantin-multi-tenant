<x-layouts.admin title="QR Meja {{ $table->code }}">
    <h1 class="text-xl font-semibold print:hidden">QR — {{ $table->label }} ({{ $table->code }})</h1>
    @if ($url)
        <div class="mt-4 rounded-lg bg-amber-100 px-4 py-3 text-sm text-amber-900 print:hidden dark:bg-amber-900/40 dark:text-amber-200">
            QR baru ditampilkan <strong>satu kali</strong>. Unduh atau cetak sekarang — token mentah tidak disimpan dan tidak dapat ditampilkan lagi.
        </div>
        <div class="mt-4 inline-flex flex-col items-center rounded-xl border border-zinc-200 bg-white p-6 text-zinc-900 dark:border-zinc-800" data-test="qr-card">
            <div class="text-sm font-bold uppercase tracking-widest">{{ config('app.name') }}</div>
            <div class="mt-1 text-2xl font-extrabold">{{ $table->label }}</div>
            @if ($table->zone)<div class="text-sm text-zinc-500">{{ $table->zone }}</div>@endif
            <div class="mt-4 w-64 [&>svg]:h-auto [&>svg]:w-full">{!! $svg !!}</div>
            <div class="mt-3 text-xs text-zinc-500">Pindai untuk memesan</div>
        </div>
        <div class="mt-4 flex flex-wrap gap-2 print:hidden">
            <a download="qr-{{ $table->code }}.svg" href="data:image/svg+xml;base64,{{ base64_encode($svg) }}"><x-button>Unduh QR (SVG)</x-button></a>
            <x-button variant="secondary" onclick="window.print()">Cetak</x-button>
        </div>
        <p class="mt-3 text-xs text-zinc-500 print:hidden">SVG adalah gambar vektor: tetap tajam pada ukuran cetak berapa pun (≥300 dpi). URL: <span class="break-all font-mono">{{ $url }}</span></p>
    @else
        <x-empty-state title="Tidak ada QR baru untuk ditampilkan"
            description="Kembali ke daftar meja dan klik Terbitkan/Regenerasi untuk memperoleh QR sekali-tampil." />
    @endif
    <div class="mt-6 print:hidden"><a class="text-sm font-medium underline" href="{{ route('admin.tables.index') }}">← Kembali ke daftar meja</a></div>
</x-layouts.admin>
