<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Exceptions;

use DomainException;

/**
 * Thrown when the financial balance equation of a sales order or quotation fails:
 * Total Order Value != sum(Line Prices - Discounts) + Taxes + Shipping Fees
 */
class InvalidFinancialBalanceException extends DomainException
{
    public static function forDiscrepancy(float $calculatedTotal, float $givenTotal, string $documentReference = ''): self
    {
        return new self("Financial balance violation for [{$documentReference}]: Grand total ({$givenTotal}) does not match balanced sum of lines, discounts, taxes, and shipping ({$calculatedTotal}).");
    }
}
