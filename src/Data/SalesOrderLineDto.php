<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Data;

use InvalidArgumentException;

final readonly class SalesOrderLineDto
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $sku,
        public string $name,
        public float $unitPrice,
        public float $quantity = 1.0,
        public float $discountAmount = 0.0,
        public float $taxRate = 0.0,
        public ?string $orderableType = null,
        public string|int|null $orderableId = null,
        public ?string $sourcingNodeId = null,
        public array $metadata = [],
    ) {
        if (trim($this->sku) === '') {
            throw new InvalidArgumentException('Order line SKU is required.');
        }
        if (trim($this->name) === '') {
            throw new InvalidArgumentException('Order line name is required.');
        }
        if ($this->quantity <= 0) {
            throw new InvalidArgumentException('Order line quantity must be greater than zero.');
        }
        if ($this->unitPrice < 0) {
            throw new InvalidArgumentException('Order line unit price cannot be negative.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function from(array $data): self
    {
        return new self(
            sku: (string) ($data['sku'] ?? throw new InvalidArgumentException('SKU is required for order line.')),
            name: (string) ($data['name'] ?? throw new InvalidArgumentException('Name is required for order line.')),
            unitPrice: (float) ($data['unit_price'] ?? 0.0),
            quantity: (float) ($data['quantity'] ?? 1.0),
            discountAmount: (float) ($data['discount_amount'] ?? 0.0),
            taxRate: (float) ($data['tax_rate'] ?? 0.0),
            orderableType: isset($data['orderable_type']) ? (string) $data['orderable_type'] : null,
            orderableId: $data['orderable_id'] ?? null,
            sourcingNodeId: isset($data['sourcing_node_id']) ? (string) $data['sourcing_node_id'] : null,
            metadata: (array) ($data['metadata'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sku' => $this->sku,
            'name' => $this->name,
            'unit_price' => $this->unitPrice,
            'quantity' => $this->quantity,
            'discount_amount' => $this->discountAmount,
            'tax_rate' => $this->taxRate,
            'orderable_type' => $this->orderableType,
            'orderable_id' => $this->orderableId,
            'sourcing_node_id' => $this->sourcingNodeId,
            'metadata' => $this->metadata,
        ];
    }
}
