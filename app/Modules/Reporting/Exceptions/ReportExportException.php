<?php

namespace App\Modules\Reporting\Exceptions;

use RuntimeException;

/**
 * Kegagalan domain ekspor laporan (UC-17).
 */
final class ReportExportException extends RuntimeException
{
    public static function rangeTooLong(): self
    {
        return new self('Rentang ekspor maksimal 12 bulan.');
    }

    public static function invalidRange(): self
    {
        return new self('Tanggal mulai harus sebelum atau sama dengan tanggal akhir.');
    }

    public static function unsupportedFormat(): self
    {
        return new self('Format ekspor harus Excel (.xlsx) atau PDF.');
    }
}
