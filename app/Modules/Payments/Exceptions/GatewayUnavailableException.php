<?php

namespace App\Modules\Payments\Exceptions;

use RuntimeException;

/**
 * Payment Gateway tidak merespons (timeout/5xx). Dilempar implementasi PaymentGateway;
 * PaymentService mencoba ulang maksimal 3 kali sebelum menyerah (UC-07 alur 1a).
 */
final class GatewayUnavailableException extends RuntimeException {}
