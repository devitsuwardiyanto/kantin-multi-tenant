<?php

namespace App\Modules\Payments\Notifications;

use App\Models\Withdrawal;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * UC-20 langkah 5: pengelola kantin diberi tahu ada pengajuan penarikan untuk diverifikasi (UC-23).
 */
class WithdrawalRequested extends Notification
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
        $tenant = $this->withdrawal->tenant()->withoutGlobalScopes()->value('display_name');

        return (new MailMessage)
            ->subject('Pengajuan penarikan '.$this->withdrawal->reference())
            ->line($tenant.' mengajukan penarikan Rp'.number_format((int) $this->withdrawal->amount, 0, ',', '.').'.')
            ->action('Verifikasi pencairan', route('admin.withdrawals.index'));
    }
}
