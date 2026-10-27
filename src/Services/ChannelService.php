<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Services;

use Illuminate\Support\Collection;
use Obelaw\Ium\Sales\Data\CreateChannelDto;
use Obelaw\Ium\Sales\Models\SalesChannel;

/**
 * Commercial Sales Channel Profile Management Service.
 */
final class ChannelService
{
    /**
     * Register or update a sales channel profile.
     */
    public function register(CreateChannelDto $dto): SalesChannel
    {
        return SalesChannel::updateOrCreate(
            ['code' => $dto->code],
            [
                'name' => $dto->name,
                'type' => $dto->type,
                'currency' => $dto->currency,
                'default_price_tier' => $dto->defaultPriceTier,
                'is_active' => $dto->isActive,
                'settings' => $dto->settings,
            ]
        );
    }

    public function findByCode(string $code): ?SalesChannel
    {
        return SalesChannel::where('code', $code)->first();
    }

    /**
     * @return Collection<int, SalesChannel>
     */
    public function all(): Collection
    {
        return SalesChannel::all();
    }
}
