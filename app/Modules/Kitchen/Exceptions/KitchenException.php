<?php

namespace App\Modules\Kitchen\Exceptions;

use RuntimeException;

/**
 * Kegagalan domain dapur (transisi status tak sah).
 */
final class KitchenException extends RuntimeException
{
    public static function invalidTransition(string $from, string $to): self
    {
        return new self("Transisi status tidak sah: {$from} → {$to}.");
    }
}
