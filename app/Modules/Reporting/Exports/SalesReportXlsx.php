<?php

namespace App\Modules\Reporting\Exports;

use RuntimeException;
use ZipArchive;

/**
 * Penulis .xlsx minimal (SpreadsheetML) memakai ZipArchive bawaan PHP — tanpa dependency.
 * Satu lembar "Laporan": kop identitas tenant, ringkasan, menu terlaris, lalu tabel transaksi.
 *
 * @phpstan-type SalesDocument array{
 *     tenant: string, canteen: string, period: string, generated_at: string,
 *     summary: array{revenue: int, transactions: int, average: int, median: int, top_menus: list<array{name: string, quantity: int}>},
 *     rows: list<array{paid_at: string, order_number: string, items: string, subtotal: int, commission: int, net: int}>
 * }
 */
final class SalesReportXlsx
{
    /**
     * @param  SalesDocument  $document
     */
    public function render(array $document): string
    {
        $rows = [
            ['LAPORAN PENJUALAN — '.$document['tenant']],
            [$document['canteen']],
            ['Periode', $document['period']],
            ['Dibuat', $document['generated_at']],
            [],
            ['Total omset', $document['summary']['revenue']],
            ['Jumlah transaksi', $document['summary']['transactions']],
            ['Rata-rata / transaksi', $document['summary']['average']],
            ['Median / transaksi', $document['summary']['median']],
            [],
            ['Menu terlaris', 'Terjual'],
        ];
        foreach ($document['summary']['top_menus'] as $menu) {
            $rows[] = [$menu['name'], $menu['quantity']];
        }
        $rows[] = [];
        $rows[] = ['Waktu bayar', 'Nomor pesanan', 'Item', 'Kotor', 'Komisi', 'Bersih'];
        foreach ($document['rows'] as $row) {
            $rows[] = [$row['paid_at'], $row['order_number'], $row['items'], $row['subtotal'], $row['commission'], $row['net']];
        }

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        if ($path === false || $zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Berkas .xlsx tidak dapat dibuat.');
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Laporan" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet($rows));
        $zip->close();

        $bytes = (string) file_get_contents($path);
        unlink($path);

        return $bytes;
    }

    /**
     * @param  list<list<string|int>>  $rows
     */
    private function sheet(array $rows): string
    {
        $xml = '';
        foreach ($rows as $r => $cells) {
            $xml .= '<row r="'.($r + 1).'">';
            foreach ($cells as $c => $value) {
                $ref = chr(ord('A') + $c).($r + 1);
                $xml .= is_int($value)
                    ? '<c r="'.$ref.'"><v>'.$value.'</v></c>'
                    : '<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
            }
            $xml .= '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<cols><col min="1" max="1" width="22" customWidth="1"/><col min="2" max="2" width="20" customWidth="1"/><col min="3" max="3" width="44" customWidth="1"/><col min="4" max="6" width="14" customWidth="1"/></cols>'
            .'<sheetData>'.$xml.'</sheetData></worksheet>';
    }
}
