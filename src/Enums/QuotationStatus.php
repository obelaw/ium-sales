<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Enums;

/**
 * Commercial states for Quotations.
 */
enum QuotationStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Converted = 'converted';
    case Expired = 'expired';

    public function isModifiable(): bool
    {
        return $this === self::Draft;
    }
}
