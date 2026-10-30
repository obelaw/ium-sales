<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Integrations\Accounting\Listeners;

use Obelaw\Ium\Sales\Events\OrderCancelled;
use Obelaw\Ium\Sales\Integrations\Accounting\SalesAccountingBridge;

final class ReverseOrderOnAccountingListener
{
    public function __construct(
        private readonly SalesAccountingBridge $bridge,
    ) {}

    public function handle(OrderCancelled $event): void
    {
        $this->bridge->reverseOrderInvoice($event->order, $event->reason);
    }
}
