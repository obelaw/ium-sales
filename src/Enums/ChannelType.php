<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Enums;

/**
 * Commercial sales channel archetypes.
 */
enum ChannelType: string
{
    case Ecommerce = 'ecommerce';
    case Mobile = 'mobile';
    case B2b = 'b2b';
    case Pos = 'pos';
    case Manual = 'manual';
    case Marketplace = 'marketplace';
}
