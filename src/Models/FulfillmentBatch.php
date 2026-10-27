<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Obelaw\Ium\Sales\Base\ModelBase;
use Obelaw\Ium\Sales\Enums\FulfillmentBatchStatus;

/**
 * Fulfillment Batch / Split Shipment Model.
 *
 * Automatically maps to table `ium_sales_fulfillment_batches`.
 */
class FulfillmentBatch extends ModelBase
{
    protected $table = 'fulfillment_batches';

    protected $fillable = [
        'batch_number',
        'sales_order_id',
        'sourcing_node_id',
        'carrier_code',
        'tracking_number',
        'service_level',
        'status',
        'shipping_address',
        'promised_ship_date',
        'shipped_at',
        'delivered_at',
        'metadata',
    ];

    protected $casts = [
        'status' => FulfillmentBatchStatus::class,
        'shipping_address' => 'array',
        'promised_ship_date' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FulfillmentBatchLine::class, 'fulfillment_batch_id');
    }
}
