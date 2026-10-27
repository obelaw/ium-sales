<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Data;

use InvalidArgumentException;

final readonly class ReceiveRmaGoodsDto
{
    /**
     * @param array<int, array{rma_line_id: int, quantity_received: float, disposition?: string, notes?: string}> $items
     */
    public function __construct(
        public array $items
    ) {
        if (empty($this->items)) {
            throw new InvalidArgumentException('Goods receipt must specify at least one inspected item.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function from(array $data): self
    {
        return new self(
            items: (array) ($data['items'] ?? throw new InvalidArgumentException('Items are required for RMA goods receipt.')),
        );
    }
}
