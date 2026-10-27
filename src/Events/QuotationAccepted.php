<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Events;

use Obelaw\Ium\Sales\Models\Quotation;

/**
 * Emitted when a customer accepts a quotation.
 */
final class QuotationAccepted
{
    public function __construct(
        public readonly Quotation $quotation
    ) {}
}
