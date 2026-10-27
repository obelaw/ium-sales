<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Events;

use Illuminate\Support\Collection;
use Obelaw\Ium\Sales\Models\SalesOrder;

/**
 * Emitted when a sales order is partitioned into multiple split fulfillment batches.
 */
final class OrderSplitted
{
    /**
     * @param Collection<int, \Obelaw\Ium\Sales\Models\FulfillmentBatch> $batches
     */
    public function __construct(
        public readonly SalesOrder $order,
        public readonly Collection $batches
    ) {}
}
