<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Data;

final readonly class MarkShippedDto
{
    public function __construct(
        public ?string $carrierCode = null,
        public ?string $trackingNumber = null,
        public ?string $shippedAt = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function from(array $data): self
    {
        return new self(
            carrierCode: isset($data['carrier_code']) ? (string) $data['carrier_code'] : null,
            trackingNumber: isset($data['tracking_number']) ? (string) $data['tracking_number'] : null,
            shippedAt: isset($data['shipped_at']) ? (string) $data['shipped_at'] : null,
        );
    }
}
