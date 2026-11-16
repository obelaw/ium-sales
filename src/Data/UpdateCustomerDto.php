<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Data;

final readonly class UpdateCustomerDto
{
    /**
     * @param array<string, mixed>|null $billingAddress
     * @param array<string, mixed>|null $shippingAddress
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $company = null,
        public ?string $taxId = null,
        public ?string $currency = null,
        public ?string $priceTier = null,
        public ?array $billingAddress = null,
        public ?array $shippingAddress = null,
        public ?bool $isActive = null,
        public ?string $notes = null,
        public ?array $metadata = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function from(array $data): self
    {
        return new self(
            name: isset($data['name']) ? (string) $data['name'] : null,
            email: array_key_exists('email', $data) ? ($data['email'] !== null ? (string) $data['email'] : null) : null,
            phone: array_key_exists('phone', $data) ? ($data['phone'] !== null ? (string) $data['phone'] : null) : null,
            company: array_key_exists('company', $data) ? ($data['company'] !== null ? (string) $data['company'] : null) : null,
            taxId: array_key_exists('tax_id', $data) ? ($data['tax_id'] !== null ? (string) $data['tax_id'] : null) : null,
            currency: isset($data['currency']) ? (string) $data['currency'] : null,
            priceTier: array_key_exists('price_tier', $data) ? ($data['price_tier'] !== null ? (string) $data['price_tier'] : null) : null,
            billingAddress: array_key_exists('billing_address', $data) ? ($data['billing_address'] !== null ? (array) $data['billing_address'] : null) : null,
            shippingAddress: array_key_exists('shipping_address', $data) ? ($data['shipping_address'] !== null ? (array) $data['shipping_address'] : null) : null,
            isActive: isset($data['is_active']) ? (bool) $data['is_active'] : null,
            notes: array_key_exists('notes', $data) ? ($data['notes'] !== null ? (string) $data['notes'] : null) : null,
            metadata: array_key_exists('metadata', $data) ? ($data['metadata'] !== null ? (array) $data['metadata'] : null) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $attributes = [];

        if ($this->name !== null) {
            $attributes['name'] = $this->name;
        }
        if ($this->email !== null) {
            $attributes['email'] = $this->email;
        }
        if ($this->phone !== null) {
            $attributes['phone'] = $this->phone;
        }
        if ($this->company !== null) {
            $attributes['company'] = $this->company;
        }
        if ($this->taxId !== null) {
            $attributes['tax_id'] = $this->taxId;
        }
        if ($this->currency !== null) {
            $attributes['currency'] = $this->currency;
        }
        if ($this->priceTier !== null) {
            $attributes['price_tier'] = $this->priceTier;
        }
        if ($this->billingAddress !== null) {
            $attributes['billing_address'] = $this->billingAddress;
        }
        if ($this->shippingAddress !== null) {
            $attributes['shipping_address'] = $this->shippingAddress;
        }
        if ($this->isActive !== null) {
            $attributes['is_active'] = $this->isActive;
        }
        if ($this->notes !== null) {
            $attributes['notes'] = $this->notes;
        }
        if ($this->metadata !== null) {
            $attributes['metadata'] = $this->metadata;
        }

        return $attributes;
    }
}
