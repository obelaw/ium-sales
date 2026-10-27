<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Enums;

use Obelaw\Ium\Contracts\IumConfigEnum;

/**
 * Type-safe configuration keys for the Sales domain.
 */
enum SalesConfigEnum: string implements IumConfigEnum
{
    case DefaultCurrency = 'ium.sales.default_currency';
    case AutoConfirm = 'ium.sales.auto_confirm';
    case PriceLockStrict = 'ium.sales.price_lock_strict';
    case AuditEnabled = 'ium.sales.audit_enabled';
    case RoutingPolicy = 'ium.sales.routing_policy';
    case AllowSplitShipments = 'ium.sales.allow_split_shipments';
    case AllowBackorders = 'ium.sales.allow_backorders';
    case RmaWindowDays = 'ium.sales.rma_window_days';
}
