<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Data;

use InvalidArgumentException;

final readonly class RecordOrderPaymentDto
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public int $salesOrderId,
        public float $amount,
        public string $paymentMethod = 'bank_transfer',
        public ?string $paymentReference = null,
        public ?string $currency = null,
        public ?string $paidAt = null,
        public ?string $notes = null,
        public array $metadata = [],
    ) {
        if ($this->salesOrderId <= 0) {
            throw new InvalidArgumentException('sales_order_id must be a positive integer.');
        }

        if ($this->amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function from(array $data): self
    {
        return new self(
            salesOrderId: (int) ($data['sales_order_id'] ?? throw new InvalidArgumentException('sales_order_id is required.')),
            amount: (float) ($data['amount'] ?? throw new InvalidArgumentException('amount is required.')),
            paymentMethod: (string) ($data['payment_method'] ?? 'bank_transfer'),
            paymentReference: isset($data['payment_reference']) ? (string) $data['payment_reference'] : null,
            currency: isset($data['currency']) ? (string) $data['currency'] : null,
            paidAt: isset($data['paid_at']) ? (string) $data['paid_at'] : null,
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
            metadata: (array) ($data['metadata'] ?? []),
        );
    }
}
