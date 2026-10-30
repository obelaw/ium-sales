<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Integrations\Accounting\Listeners;

use Obelaw\Ium\Sales\Events\SalesOrderConfirmed;
use Obelaw\Ium\Sales\Integrations\Accounting\SalesAccountingBridge;

final class PostOrderInvoiceToAccountingListener
{
    public function __construct(
        private readonly SalesAccountingBridge $bridge,
    ) {}

    public function handle(SalesOrderConfirmed $event): void
    {
        $this->bridge->postOrderInvoice($event->order);
    }
}
