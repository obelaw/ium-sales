<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Exceptions;

use RuntimeException;

/**
 * Thrown when an attempt is made to mutate commercial terms or prices
 * on a confirmed sales order in violation of the Price-Lock Invariant.
 */
class OrderPriceLockedException extends RuntimeException
{
    public static function forOrder(string $orderNumber, string $operation = 'edit'): self
    {
        return new self("Cannot {$operation} sales order [{$orderNumber}]: commercial prices and terms are permanently frozen upon confirmation. Use credit notes or amendment orders.");
    }
}
