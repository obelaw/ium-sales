<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Events;

use Obelaw\Ium\Sales\Models\SalesOrder;
use Obelaw\Ium\Sales\Models\SalesOrderPayment;

/**
 * Emitted when a customer payment is recorded against a sales order.
 */
final class OrderPaymentRecorded
{
    public function __construct(
        public readonly SalesOrder $order,
        public readonly SalesOrderPayment $payment,
    ) {}
}
