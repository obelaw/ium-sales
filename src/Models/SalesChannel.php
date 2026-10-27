<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Obelaw\Ium\Sales\Base\ModelBase;
use Obelaw\Ium\Sales\Enums\ChannelType;

/**
 * Sales Channel Model.
 *
 * Automatically maps to table `ium_sales_channels`.
 */
class SalesChannel extends ModelBase
{
    protected $table = 'channels';

    protected $fillable = [
        'code',
        'name',
        'type',
        'currency',
        'default_price_tier',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'type' => ChannelType::class,
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(SalesOrder::class, 'channel_code', 'code');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class, 'channel_code', 'code');
    }
}
