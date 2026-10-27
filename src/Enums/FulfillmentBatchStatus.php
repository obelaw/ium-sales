<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Enums;

/**
 * Lifecycle states for fulfillment split batches.
 */
enum FulfillmentBatchStatus: string
{
    case Pending = 'pending';
    case Allocated = 'allocated';
    case Released = 'released';
    case Picked = 'picked';
    case Packed = 'packed';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
}
