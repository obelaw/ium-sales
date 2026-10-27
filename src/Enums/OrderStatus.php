<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Enums;

/**
 * Lifecycle states for Sales Orders.
 */
enum OrderStatus: string
{
    case Draft = 'draft';
    case PendingConfirmation = 'pending_confirmation';
    case Confirmed = 'confirmed';
    case Allocated = 'allocated';
    case Released = 'released';
    case PartiallyShipped = 'partially_shipped';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case CancellationPending = 'cancellation_pending';

    public function isLocked(): bool
    {
        return !in_array($this, [self::Draft, self::PendingConfirmation], true);
    }

    public function isCancellablePreRelease(): bool
    {
        return in_array($this, [self::Draft, self::PendingConfirmation, self::Confirmed, self::Allocated], true);
    }
}
