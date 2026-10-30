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

    // Optional Accounting Integration
    case AccountingEnabled = 'ium.sales.accounting.enabled';
    case AccountingAutoPost = 'ium.sales.accounting.auto_post';
    case AccountingReceivablesCode = 'ium.sales.accounting.accounts.receivables';
    case AccountingRevenueCode = 'ium.sales.accounting.accounts.revenue';
    case AccountingTaxPayableCode = 'ium.sales.accounting.accounts.tax_payable';
    case AccountingShippingCode = 'ium.sales.accounting.accounts.shipping';
    case AccountingBankCode = 'ium.sales.accounting.accounts.bank';
    case AccountingCogsCode = 'ium.sales.accounting.accounts.cogs';
    case AccountingInventoryCode = 'ium.sales.accounting.accounts.inventory';
    case AccountingReturnsCode = 'ium.sales.accounting.accounts.returns';
}
