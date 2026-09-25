<?php

namespace App\Modules\Ordering\Exceptions;

use RuntimeException;

/**
 * Pengiriman notifikasi gagal (provider menolak/timeout). Job mencoba ulang maksimal 3 kali
 * dengan jeda bertingkat; kegagalan akhir hanya dicatat — alur pesanan tidak terganggu (UC-12 4a/4b).
 */
final class NotificationFailedException extends RuntimeException {}
