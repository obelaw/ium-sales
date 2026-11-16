<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Data;

use InvalidArgumentException;

final readonly class CreateCustomerDto
{
    /**
     * @param array<string, mixed>|null $billingAddress
     * @param array<string, mixed>|null $shippingAddress
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $name,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $company = null,
        public ?string $taxId = null,
        public string $currency = 'USD',
        public ?string $priceTier = null,
        public ?array $billingAddress = null,
        public ?array $shippingAddress = null,
        public bool $isActive = true,
        public ?string $notes = null,
        public array $metadata = [],
    ) {
        if (trim($this->name) === '') {
            throw new InvalidArgumentException('Customer name is required.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function from(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? throw new InvalidArgumentException('Customer name is required.')),
            email: isset($data['email']) && trim((string) $data['email']) !== '' ? (string) $data['email'] : null,
            phone: isset($data['phone']) && trim((string) $data['phone']) !== '' ? (string) $data['phone'] : null,
            company: isset($data['company']) && trim((string) $data['company']) !== '' ? (string) $data['company'] : null,
            taxId: isset($data['tax_id']) && trim((string) $data['tax_id']) !== '' ? (string) $data['tax_id'] : null,
            currency: (string) ($data['currency'] ?? 'USD'),
            priceTier: isset($data['price_tier']) ? (string) $data['price_tier'] : null,
            billingAddress: isset($data['billing_address']) ? (array) $data['billing_address'] : null,
            shippingAddress: isset($data['shipping_address']) ? (array) $data['shipping_address'] : null,
            isActive: (bool) ($data['is_active'] ?? true),
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
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'tax_id' => $this->taxId,
            'currency' => $this->currency,
            'price_tier' => $this->priceTier,
            'billing_address' => $this->billingAddress,
            'shipping_address' => $this->shippingAddress,
            'is_active' => $this->isActive,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
        ];
    }
}
