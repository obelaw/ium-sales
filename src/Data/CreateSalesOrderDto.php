<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Data;

use InvalidArgumentException;

final readonly class CreateSalesOrderDto
{
    /**
     * @param array<int, SalesOrderLineDto> $lines
     * @param array<string, mixed>|null $billingAddress
     * @param array<string, mixed>|null $shippingAddress
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public array $lines,
        public ?string $orderNumber = null,
        public ?string $customerType = null,
        public string|int|null $customerId = null,
        public ?string $channelCode = null,
        public string $currency = 'USD',
        public string $paymentStatus = 'pending',
        public ?string $paymentTerms = null,
        public ?array $billingAddress = null,
        public ?array $shippingAddress = null,
        public float $shippingTotal = 0.0,
        public ?int $quotationId = null,
        public array $metadata = [],
    ) {
        if (empty($this->lines)) {
            throw new InvalidArgumentException('Sales order must contain at least one line item.');
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
            fn(mixed $line) => $line instanceof SalesOrderLineDto ? $line : SalesOrderLineDto::from((array) $line),
            $rawLines
        );

        return new self(
            lines: $lines,
            orderNumber: isset($data['order_number']) ? (string) $data['order_number'] : null,
            customerType: isset($data['customer_type']) ? (string) $data['customer_type'] : null,
            customerId: $data['customer_id'] ?? null,
            channelCode: isset($data['channel_code']) ? (string) $data['channel_code'] : null,
            currency: (string) ($data['currency'] ?? 'USD'),
            paymentStatus: (string) ($data['payment_status'] ?? 'pending'),
            paymentTerms: isset($data['payment_terms']) ? (string) $data['payment_terms'] : null,
            billingAddress: isset($data['billing_address']) ? (array) $data['billing_address'] : null,
            shippingAddress: isset($data['shipping_address']) ? (array) $data['shipping_address'] : null,
            shippingTotal: (float) ($data['shipping_total'] ?? 0.0),
            quotationId: isset($data['quotation_id']) ? (int) $data['quotation_id'] : null,
            metadata: (array) ($data['metadata'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'order_number' => $this->orderNumber,
            'customer_type' => $this->customerType,
            'customer_id' => $this->customerId,
            'channel_code' => $this->channelCode,
            'currency' => $this->currency,
            'payment_status' => $this->paymentStatus,
            'payment_terms' => $this->paymentTerms,
            'billing_address' => $this->billingAddress,
            'shipping_address' => $this->shippingAddress,
            'shipping_total' => $this->shippingTotal,
            'quotation_id' => $this->quotationId,
            'lines' => array_map(fn(SalesOrderLineDto $line) => $line->toArray(), $this->lines),
            'metadata' => $this->metadata,
        ];
    }
}
