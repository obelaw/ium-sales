<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Obelaw\Ium\Sales\Base\ModelBase;
use Obelaw\Ium\Sales\Enums\RmaDisposition;

/**
 * RMA Line Item Model.
 *
 * Automatically maps to table `ium_sales_rma_lines`.
 */
class RmaLine extends ModelBase
{
    protected $table = 'rma_lines';

    protected $fillable = [
        'rma_id',
        'sales_order_line_id',
        'quantity_requested',
        'quantity_received',
        'quantity_approved',
        'disposition',
        'refund_amount',
        'notes',
    ];

    protected $casts = [
        'quantity_requested' => 'float',
        'quantity_received' => 'float',
        'quantity_approved' => 'float',
        'disposition' => RmaDisposition::class,
        'refund_amount' => 'float',
    ];

    public function rma(): BelongsTo
    {
        return $this->belongsTo(ReturnMerchandiseAuthorization::class, 'rma_id');
    }

    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class, 'sales_order_line_id');
    }
}
