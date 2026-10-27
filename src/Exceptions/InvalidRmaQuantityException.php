<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Exceptions;

use DomainException;

/**
 * Thrown when RMA return requested quantity exceeds shipped or eligible quantity.
 */
class InvalidRmaQuantityException extends DomainException
{
    public static function forLine(string $sku, float $requested, float $maxEligible): self
    {
        return new self("Invalid return quantity for item [{$sku}]: requested {$requested}, max returnable {$maxEligible}.");
    }
}
