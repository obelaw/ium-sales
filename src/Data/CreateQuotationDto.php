<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Data;

use InvalidArgumentException;

final readonly class CreateQuotationDto
{
    /**
     * @param array<int, QuotationLineDto> $lines
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public array $lines,
        public ?string $code = null,
        public ?string $customerType = null,
        public string|int|null $customerId = null,
        public ?string $channelCode = null,
        public string $currency = 'USD',
        public ?string $validUntil = null,
        public float $shippingTotal = 0.0,
        public ?string $notes = null,
        public array $metadata = [],
    ) {
        if (empty($this->lines)) {
            throw new InvalidArgumentException('Quotation must contain at least one line item.');
        }
        if ($this->shippingTotal < 0) {
            throw new InvalidArgumentException('Shipping total cannot be negative.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function from(array $data): self
    {
        $rawLines = (array) ($data['lines'] ?? []);
        $lines = array_map(
            fn(mixed $line) => $line instanceof QuotationLineDto ? $line : QuotationLineDto::from((array) $line),
            $rawLines
        );

        return new self(
            lines: $lines,
            code: isset($data['code']) ? (string) $data['code'] : null,
            customerType: isset($data['customer_type']) ? (string) $data['customer_type'] : null,
            customerId: $data['customer_id'] ?? null,
            channelCode: isset($data['channel_code']) ? (string) $data['channel_code'] : null,
            currency: (string) ($data['currency'] ?? 'USD'),
            validUntil: isset($data['valid_until']) ? (string) $data['valid_until'] : null,
            shippingTotal: (float) ($data['shipping_total'] ?? 0.0),
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
            metadata: (array) ($data['metadata'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'customer_type' => $this->customerType,
            'customer_id' => $this->customerId,
            'channel_code' => $this->channelCode,
            'currency' => $this->currency,
            'valid_until' => $this->validUntil,
            'shipping_total' => $this->shippingTotal,
            'notes' => $this->notes,
            'lines' => array_map(fn(QuotationLineDto $line) => $line->toArray(), $this->lines),
            'metadata' => $this->metadata,
        ];
    }
}
