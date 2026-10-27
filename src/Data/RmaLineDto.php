<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Data;

use InvalidArgumentException;

final readonly class RmaLineDto
{
    public function __construct(
        public int $salesOrderLineId,
        public float $quantityRequested,
        public ?string $notes = null,
    ) {
        if ($this->salesOrderLineId <= 0) {
            throw new InvalidArgumentException('Valid sales order line ID is required for RMA line.');
        }
        if ($this->quantityRequested <= 0) {
            throw new InvalidArgumentException('Requested return quantity must be greater than zero.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function from(array $data): self
    {
        return new self(
            salesOrderLineId: (int) ($data['sales_order_line_id'] ?? throw new InvalidArgumentException('Sales order line ID is required.')),
            quantityRequested: (float) ($data['quantity_requested'] ?? throw new InvalidArgumentException('Requested quantity is required.')),
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
        );
    }
}
