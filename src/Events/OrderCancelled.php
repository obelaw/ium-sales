<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Events;

use Obelaw\Ium\Sales\Models\SalesOrder;

/**
 * Emitted when an order is cancelled according to domain governance rules.
 */
final class OrderCancelled
{
    public function __construct(
        public readonly SalesOrder $order,
        public readonly string $reason
    ) {}
}
