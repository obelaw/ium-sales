<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Data;

use InvalidArgumentException;

final readonly class CreateChannelDto
{
    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(
        public string $code,
        public string $name,
        public string $type = 'ecommerce',
        public string $currency = 'USD',
        public ?string $defaultPriceTier = null,
        public bool $isActive = true,
        public array $settings = [],
    ) {
        if (trim($this->code) === '') {
            throw new InvalidArgumentException('Channel code is required.');
        }
        if (trim($this->name) === '') {
            throw new InvalidArgumentException('Channel name is required.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function from(array $data): self
    {
        return new self(
            code: (string) ($data['code'] ?? throw new InvalidArgumentException('Channel code is required.')),
            name: (string) ($data['name'] ?? throw new InvalidArgumentException('Channel name is required.')),
            type: (string) ($data['type'] ?? 'ecommerce'),
            currency: (string) ($data['currency'] ?? 'USD'),
            defaultPriceTier: isset($data['default_price_tier']) ? (string) $data['default_price_tier'] : null,
            isActive: (bool) ($data['is_active'] ?? true),
            settings: (array) ($data['settings'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'currency' => $this->currency,
            'default_price_tier' => $this->defaultPriceTier,
            'is_active' => $this->isActive,
            'settings' => $this->settings,
        ];
    }
}
