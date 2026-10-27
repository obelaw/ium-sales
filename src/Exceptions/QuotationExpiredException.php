<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Exceptions;

use DomainException;

/**
 * Thrown when attempting to accept or convert an expired quotation.
 */
class QuotationExpiredException extends DomainException
{
    public static function forQuotation(string $quoteCode, string $expiredAt): self
    {
        return new self("Quotation [{$quoteCode}] expired on [{$expiredAt}] and can no longer be accepted or converted.");
    }
}
