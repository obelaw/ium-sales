<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Events;

use Obelaw\Ium\Sales\Models\ReturnMerchandiseAuthorization;

/**
 * Emitted when an RMA return inspection is approved for credit / refund compensation.
 */
final class RmaCreditApproved
{
    public function __construct(
        public readonly ReturnMerchandiseAuthorization $rma,
        public readonly float $refundAmount,
    ) {}
}
