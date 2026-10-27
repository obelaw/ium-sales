<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Obelaw\Ium\Sales\Base\ModelBase;

/**
 * Commercial Quotation Line Item.
 *
 * Automatically maps to table `ium_sales_quotation_lines`.
 */
class QuotationLine extends ModelBase
{
    protected $table = 'quotation_lines';

    protected $fillable = [
        'quotation_id',
        'line_number',
        'orderable_type',
        'orderable_id',
        'sku',
        'name',
        'unit_price',
        'quantity',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'metadata',
    ];

    protected $casts = [
        'line_number' => 'integer',
        'unit_price' => 'float',
        'quantity' => 'float',
        'discount_amount' => 'float',
        'tax_rate' => 'float',
        'tax_amount' => 'float',
        'total_amount' => 'float',
        'metadata' => 'array',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function orderable(): MorphTo
    {
        return $this->morphTo();
    }
}
