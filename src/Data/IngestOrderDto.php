<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Data;

use InvalidArgumentException;

final readonly class IngestOrderDto
{
    /**
     * @param array<int, SalesOrderLineDto> $lines
     * @param array<string, mixed>|null $billingAddress
     * @param array<string, mixed>|null $shippingAddress
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $channelCode,
        public array $lines,
        public ?string $channelOrderId = null,
        public ?string $customerType = null,
        public string|int|null $customerId = null,
        public ?string $customerEmail = null,
        public ?string $customerName = null,
        public string $currency = 'USD',
        public ?array $billingAddress = null,
        public ?array $shippingAddress = null,
        public float $shippingTotal = 0.0,
        public string $paymentStatus = 'pending',
        public ?string $paymentTerms = null,
        public array $metadata = [],
    ) {
        if (trim($this->channelCode) === '') {
            throw new InvalidArgumentException('Channel code is required for omnichannel ingestion.');
        }
        if (empty($this->lines)) {
            throw new InvalidArgumentException('Ingested order must contain at least one line item.');
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
            channelCode: (string) ($data['channel_code'] ?? throw new InvalidArgumentException('Channel code is required.')),
            lines: $lines,
            channelOrderId: isset($data['channel_order_id']) ? (string) $data['channel_order_id'] : null,
            customerType: isset($data['customer_type']) ? (string) $data['customer_type'] : null,
            customerId: $data['customer_id'] ?? null,
            customerEmail: isset($data['customer_email']) ? (string) $data['customer_email'] : null,
            customerName: isset($data['customer_name']) ? (string) $data['customer_name'] : null,
            currency: (string) ($data['currency'] ?? 'USD'),
            billingAddress: isset($data['billing_address']) ? (array) $data['billing_address'] : null,
            shippingAddress: isset($data['shipping_address']) ? (array) $data['shipping_address'] : null,
            shippingTotal: (float) ($data['shipping_total'] ?? 0.0),
            paymentStatus: (string) ($data['payment_status'] ?? 'pending'),
            paymentTerms: isset($data['payment_terms']) ? (string) $data['payment_terms'] : null,
            metadata: (array) ($data['metadata'] ?? []),
        );
    }

    public function toCreateSalesOrderDto(): CreateSalesOrderDto
    {
        $meta = $this->metadata;
        if ($this->channelOrderId) {
            $meta['channel_order_id'] = $this->channelOrderId;
        }
        if ($this->customerEmail) {
            $meta['customer_email'] = $this->customerEmail;
        }
        if ($this->customerName) {
            $meta['customer_name'] = $this->customerName;
        }

        return new CreateSalesOrderDto(
            lines: $this->lines,
            orderNumber: null,
            customerType: $this->customerType,
            customerId: $this->customerId,
            channelCode: $this->channelCode,
            currency: $this->currency,
            paymentStatus: $this->paymentStatus,
            paymentTerms: $this->paymentTerms,
            billingAddress: $this->billingAddress,
            shippingAddress: $this->shippingAddress,
            shippingTotal: $this->shippingTotal,
            metadata: $meta,
        );
    }
}
