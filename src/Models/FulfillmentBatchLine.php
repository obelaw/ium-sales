<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Obelaw\Ium\Sales\Base\ModelBase;

/**
 * Fulfillment Batch Line Item.
 *
 * Automatically maps to table `ium_sales_fulfillment_batch_lines`.
 */
class FulfillmentBatchLine extends ModelBase
{
    protected $table = 'fulfillment_batch_lines';

    protected $fillable = [
        'fulfillment_batch_id',
        'sales_order_line_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'float',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(FulfillmentBatch::class, 'fulfillment_batch_id');
    }

    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class, 'sales_order_line_id');
    }
}
