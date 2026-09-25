<?php

namespace App\Modules\Reporting\Exports;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * PDF laporan penjualan (UC-17) dari view `reporting::exports.sales-report` memakai dompdf.
 * Remote resource dimatikan: PDF hanya berisi data yang diberikan.
 *
 * @phpstan-import-type SalesDocument from SalesReportXlsx
 */
final class SalesReportPdf
{
    /**
     * @param  SalesDocument  $document
     */
    public function render(array $document): string
    {
        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('reporting::exports.sales-report', ['document' => $document])->render());
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
