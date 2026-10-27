<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Events;

use Obelaw\Ium\Sales\Models\ReturnMerchandiseAuthorization;

/**
 * Emitted when an RMA return request is authorized.
 */
final class ReturnAuthorized
{
    public function __construct(
        public readonly ReturnMerchandiseAuthorization $rma
    ) {}
}
