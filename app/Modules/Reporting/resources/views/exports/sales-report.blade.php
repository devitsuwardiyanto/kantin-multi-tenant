@php($rupiah = fn (int $n) => 'Rp'.number_format($n, 0, ',', '.'))
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Penjualan — {{ $document['tenant'] }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #201e1d; }
        .kop { border-bottom: 3px solid #ec3013; padding-bottom: 6px; margin-bottom: 12px; }
        .kop h1 { font-size: 16px; margin: 0; }
        .muted { color: #6b6866; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th { text-align: left; background: #f0efee; padding: 4px; font-size: 9px; text-transform: uppercase; }
        td { padding: 4px; border-bottom: 1px solid #e3e1df; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .cards td { border: 1px solid #201e1d; width: 25%; }
        .cards strong { font-size: 13px; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>LAPORAN PENJUALAN — {{ $document['tenant'] }}</h1>
        <div class="muted">{{ $document['canteen'] }} · Periode {{ $document['period'] }} · dibuat {{ $document['generated_at'] }}</div>
    </div>

    <table class="cards">
        <tr>
            <td>Total omset<br><strong>{{ $rupiah($document['summary']['revenue']) }}</strong></td>
            <td>Jumlah transaksi<br><strong>{{ $document['summary']['transactions'] }}</strong></td>
            <td>Rata-rata / transaksi<br><strong>{{ $rupiah($document['summary']['average']) }}</strong></td>
            <td>Median / transaksi<br><strong>{{ $rupiah($document['summary']['median']) }}</strong></td>
        </tr>
    </table>

    <table>
        <tr><th>Menu terlaris</th><th class="num">Terjual</th></tr>
        @forelse ($document['summary']['top_menus'] as $menu)
            <tr><td>{{ $menu['name'] }}</td><td class="num">{{ $menu['quantity'] }}</td></tr>
        @empty
            <tr><td colspan="2" class="muted">Tidak ada penjualan pada periode ini.</td></tr>
        @endforelse
    </table>

    <table>
        <tr><th>Waktu bayar</th><th>Nomor</th><th>Item</th><th class="num">Kotor</th><th class="num">Komisi</th><th class="num">Bersih</th></tr>
        @foreach ($document['rows'] as $row)
            <tr>
                <td>{{ $row['paid_at'] }}</td>
                <td>#{{ $row['order_number'] }}</td>
                <td>{{ $row['items'] }}</td>
                <td class="num">{{ $rupiah($row['subtotal']) }}</td>
                <td class="num">{{ $rupiah($row['commission']) }}</td>
                <td class="num">{{ $rupiah($row['net']) }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>
