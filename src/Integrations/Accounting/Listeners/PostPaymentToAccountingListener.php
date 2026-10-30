<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Integrations\Accounting\Listeners;

use Obelaw\Ium\Sales\Events\OrderPaymentRecorded;
use Obelaw\Ium\Sales\Integrations\Accounting\SalesAccountingBridge;

final class PostPaymentToAccountingListener
{
    public function __construct(
        private readonly SalesAccountingBridge $bridge,
    ) {}

    public function handle(OrderPaymentRecorded $event): void
    {
        $this->bridge->postPaymentReceipt($event->order, $event->payment);
    }
}
