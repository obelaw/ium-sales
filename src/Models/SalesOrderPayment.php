<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Obelaw\Ium\Sales\Base\ModelBase;

/**
 * Sales Order Payment Record.
 *
 * Automatically maps to table `ium_sales_order_payments`.
 */
class SalesOrderPayment extends ModelBase
{
    protected $table = 'order_payments';

    protected $fillable = [
        'sales_order_id',
        'payment_reference',
        'payment_method',
        'amount',
        'amount_minor',
        'currency',
        'status',
        'paid_at',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'float',
        'amount_minor' => 'integer',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }
}
