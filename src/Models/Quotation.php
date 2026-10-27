<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Obelaw\Ium\Sales\Base\ModelBase;
use Obelaw\Ium\Sales\Enums\QuotationStatus;

/**
 * Commercial Quotation / Proforma Model.
 *
 * Automatically maps to table `ium_sales_quotations`.
 */
class Quotation extends ModelBase
{
    protected $table = 'quotations';

    protected $fillable = [
        'code',
        'customer_type',
        'customer_id',
        'channel_code',
        'currency',
        'status',
        'valid_until',
        'subtotal',
        'discount_total',
        'tax_total',
        'shipping_total',
        'grand_total',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'status' => QuotationStatus::class,
        'valid_until' => 'datetime',
        'subtotal' => 'float',
        'discount_total' => 'float',
        'tax_total' => 'float',
        'shipping_total' => 'float',
        'grand_total' => 'float',
        'metadata' => 'array',
    ];

    public function customer(): MorphTo
    {
        return $this->morphTo();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuotationLine::class, 'quotation_id')->orderBy('line_number');
    }

    public function salesOrder(): HasOne
    {
        return $this->hasOne(SalesOrder::class, 'quotation_id');
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->isPast();
    }
}
