<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Exceptions;

use DomainException;

/**
 * Thrown when Available-To-Promise (ATP) inventory is insufficient
 * during hard allocation at a target sourcing node.
 */
class InsufficientInventoryException extends DomainException
{
    public static function forItem(string $itemSku, float $requested, float $available, ?string $nodeId = null): self
    {
        $nodeMsg = $nodeId ? " at sourcing node [{$nodeId}]" : '';
        return new self("Insufficient ATP inventory for item [{$itemSku}]{$nodeMsg}: requested {$requested}, available {$available}.");
    }
}
