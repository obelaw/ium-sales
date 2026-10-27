<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Events;

use Obelaw\Ium\Sales\Models\SalesOrder;

/**
 * Emitted when a sales order is confirmed and its commercial terms are permanently locked.
 */
final class SalesOrderConfirmed
{
    public function __construct(
        public readonly SalesOrder $order
    ) {}
}
