<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Data;

use InvalidArgumentException;

final readonly class CreateRmaDto
{
    /**
     * @param array<int, RmaLineDto> $lines
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public int $salesOrderId,
        public string $reasonCode,
        public array $lines,
        public ?string $reasonDescription = null,
        public string $returnType = 'refund', // refund, replacement, store_credit
        public array $metadata = [],
    ) {
        if ($this->salesOrderId <= 0) {
            throw new InvalidArgumentException('Valid sales order ID is required for RMA.');
        }
        if (trim($this->reasonCode) === '') {
            throw new InvalidArgumentException('Reason code is mandatory for return authorization.');
        }
        if (empty($this->lines)) {
            throw new InvalidArgumentException('RMA must specify at least one return line.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function from(array $data): self
    {
        $rawLines = (array) ($data['lines'] ?? []);
        $lines = array_map(
            fn(mixed $line) => $line instanceof RmaLineDto ? $line : RmaLineDto::from((array) $line),
            $rawLines
        );

        return new self(
            salesOrderId: (int) ($data['sales_order_id'] ?? throw new InvalidArgumentException('Sales order ID is required.')),
            reasonCode: (string) ($data['reason_code'] ?? throw new InvalidArgumentException('Reason code is required.')),
            lines: $lines,
            reasonDescription: isset($data['reason_description']) ? (string) $data['reason_description'] : null,
            returnType: (string) ($data['return_type'] ?? 'refund'),
            metadata: (array) ($data['metadata'] ?? []),
        );
    }
}
