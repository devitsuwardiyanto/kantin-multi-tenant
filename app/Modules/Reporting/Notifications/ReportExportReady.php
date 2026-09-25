<?php

namespace App\Modules\Reporting\Notifications;

use App\Models\ReportExport;
use App\Modules\Reporting\Services\ReportExportService;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * UC-17 alur 2a: berkas ekspor bervolume besar dikirim tautannya lewat surel.
 */
class ReportExportReady extends Notification
{
    public function __construct(public ReportExport $export) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $service = app(ReportExportService::class);

        return (new MailMessage)
            ->subject('Laporan penjualan siap diunduh')
            ->line('Berkas '.strtoupper($this->export->format).' untuk periode '.$this->export->date_from->toDateString().' s.d. '.$this->export->date_to->toDateString().' ('.$this->export->row_count.' transaksi) sudah selesai dibuat.')
            ->action('Unduh laporan', (string) $service->downloadUrl($this->export))
            ->line('Tautan berlaku '.ReportExportService::LINK_TTL_HOURS.' jam.');
    }
}
