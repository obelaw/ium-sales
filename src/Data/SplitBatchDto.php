<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Data;

use InvalidArgumentException;

final readonly class SplitBatchDto
{
    /**
     * @param array<int, array{order_line_id: int, quantity: float}> $items
     * @param array<string, mixed>|null $shippingAddress
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $sourcingNodeId,
        public array $items,
        public ?string $carrierCode = null,
        public ?string $serviceLevel = null,
        public ?array $shippingAddress = null,
        public ?string $promisedShipDate = null,
        public array $metadata = [],
    ) {
        if (trim($this->sourcingNodeId) === '') {
            throw new InvalidArgumentException('Sourcing node ID is required for a fulfillment batch.');
        }
        if (empty($this->items)) {
            throw new InvalidArgumentException('Fulfillment batch must contain at least one item.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function from(array $data): self
    {
        return new self(
            sourcingNodeId: (string) ($data['sourcing_node_id'] ?? throw new InvalidArgumentException('Sourcing node ID is required.')),
            items: (array) ($data['items'] ?? []),
            carrierCode: isset($data['carrier_code']) ? (string) $data['carrier_code'] : null,
            serviceLevel: isset($data['service_level']) ? (string) $data['service_level'] : null,
            shippingAddress: isset($data['shipping_address']) ? (array) $data['shipping_address'] : null,
            promisedShipDate: isset($data['promised_ship_date']) ? (string) $data['promised_ship_date'] : null,
            metadata: (array) ($data['metadata'] ?? []),
        );
    }
}
