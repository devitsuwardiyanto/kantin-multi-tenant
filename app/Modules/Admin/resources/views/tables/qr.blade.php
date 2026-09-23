<x-layouts.admin title="QR Meja {{ $table->code }}">
    <h1 class="text-xl font-semibold">QR — {{ $table->label }} ({{ $table->code }})</h1>
    @if ($url)
        <div class="mt-4 rounded-lg bg-amber-100 px-4 py-3 text-sm text-amber-900 dark:bg-amber-900/40 dark:text-amber-200">
            Token QR baru ditampilkan <strong>satu kali</strong>. Cetak/simpan URL ini sekarang — token mentah tidak dapat ditampilkan lagi.
        </div>
        <div class="mt-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
            <p class="text-sm text-zinc-500">URL untuk QR (arahkan generator QR ke URL ini):</p>
            <p class="mt-1 break-all font-mono text-sm">{{ $url }}</p>
        </div>
        <p class="mt-3 text-xs text-zinc-500">Catatan: raster QR (PNG/SVG) memerlukan library <code>chillerlan/php-qrcode</code> yang belum dipasang (menunggu persetujuan dependency). Keamanan token tidak bergantung pada gambar.</p>
    @else
        <x-empty-state title="Tidak ada token baru untuk ditampilkan"
            description="Kembali ke daftar meja dan klik Rotate/Terbitkan QR untuk memperoleh URL sekali-tampil." />
    @endif
    <div class="mt-6"><a class="text-sm font-medium underline" href="{{ route('admin.tables.index') }}">← Kembali ke daftar meja</a></div>
</x-layouts.admin>
