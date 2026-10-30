<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Events;

use Obelaw\Ium\Sales\Models\SalesOrder;

/**
 * Emitted when an invoice is issued for a sales order.
 */
final class OrderInvoiced
{
    public function __construct(
        public readonly SalesOrder $order,
        public readonly string $invoiceNumber,
    ) {}
}
