<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Events;

use Obelaw\Ium\Sales\Models\FulfillmentBatch;

/**
 * Emitted when a fulfillment batch is released to a sourcing node (WMS).
 */
final class FulfillmentBatchReleased
{
    public function __construct(
        public readonly FulfillmentBatch $batch
    ) {}
}
