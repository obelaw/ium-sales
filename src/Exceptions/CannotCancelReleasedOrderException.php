<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Exceptions;

use DomainException;

/**
 * Thrown when an order or line is cancelled after being released to active fulfillment.
 */
class CannotCancelReleasedOrderException extends DomainException
{
    public static function forOrder(string $orderNumber, string $state): self
    {
        return new self("Cannot cancel sales order [{$orderNumber}] in [{$state}] state: order has already been released for warehouse fulfillment. Intercept fulfillment batch and initiate a formal RMA / Credit Note.");
    }
}
