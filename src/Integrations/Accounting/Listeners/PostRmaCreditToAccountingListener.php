<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Integrations\Accounting\Listeners;

use Obelaw\Ium\Sales\Events\RmaCreditApproved;
use Obelaw\Ium\Sales\Integrations\Accounting\SalesAccountingBridge;

final class PostRmaCreditToAccountingListener
{
    public function __construct(
        private readonly SalesAccountingBridge $bridge,
    ) {}

    public function handle(RmaCreditApproved $event): void
    {
        $this->bridge->postRmaCreditNote($event->rma, $event->refundAmount);
    }
}
