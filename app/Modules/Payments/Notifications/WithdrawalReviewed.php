<?php

namespace App\Modules\Payments\Notifications;

use App\Models\Withdrawal;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * UC-23 langkah 6 / alur 4a: tenant diberi tahu penarikan dicairkan atau ditolak (beserta alasan).
 */
class WithdrawalReviewed extends Notification
{
    public function __construct(public Withdrawal $withdrawal) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = 'Rp'.number_format((int) $this->withdrawal->amount, 0, ',', '.');
        $message = (new MailMessage)->subject('Penarikan '.$this->withdrawal->reference().' '.($this->withdrawal->status === 'paid' ? 'dicairkan' : 'ditolak'));

        return $this->withdrawal->status === 'paid'
            ? $message->line('Penarikan '.$amount.' telah dicairkan ke rekening Anda. Bukti transfer tersimpan di riwayat pengajuan.')
            : $message->line('Penarikan '.$amount.' ditolak: '.$this->withdrawal->review_note)->line('Dana dikembalikan ke saldo tersedia.');
    }
}
