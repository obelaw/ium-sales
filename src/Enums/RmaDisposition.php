<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Enums;

/**
 * Inspection disposition for returned items.
 */
enum RmaDisposition: string
{
    case Restock = 'restock';
    case Scrap = 'scrap';
    case Repair = 'repair';
    case Pending = 'pending';
}
