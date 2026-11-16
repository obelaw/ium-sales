<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Models;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Obelaw\Ium\Sales\Base\ModelBase;

/**
 * Customer Model.
 *
 * Automatically maps to table `ium_sales_customers`.
 */
class Customer extends ModelBase
{
    protected $table = 'customers';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'tax_id',
        'currency',
        'price_tier',
        'billing_address',
        'shipping_address',
        'is_active',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Polymorphic relation to orders.
     */
    public function orders(): MorphMany
    {
        return $this->morphMany(SalesOrder::class, 'customer');
    }

    /**
     * Polymorphic relation to quotations.
     */
    public function quotations(): MorphMany
    {
        return $this->morphMany(Quotation::class, 'customer');
    }

    /**
     * Polymorphic relation to RMAs.
     */
    public function rmas(): MorphMany
    {
        return $this->morphMany(ReturnMerchandiseAuthorization::class, 'customer');
    }
}
