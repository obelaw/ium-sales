<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Obelaw\Ium\Sales\Base\ModelBase;
use Obelaw\Ium\Sales\Enums\OrderStatus;
use Obelaw\Ium\Sales\Exceptions\OrderPriceLockedException;

/**
 * Sales Order Line Model.
 *
 * Automatically maps to table `ium_sales_order_lines`.
 */
class SalesOrderLine extends ModelBase
{
    protected $table = 'order_lines';

    protected $fillable = [
        'sales_order_id',
        'line_number',
        'orderable_type',
        'orderable_id',
        'sku',
        'name',
        'unit_price',
        'quantity',
        'allocated_quantity',
        'shipped_quantity',
        'returned_quantity',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'status',
        'sourcing_node_id',
        'metadata',
    ];

    protected $casts = [
        'line_number' => 'integer',
        'unit_price' => 'float',
        'quantity' => 'float',
        'allocated_quantity' => 'float',
        'shipped_quantity' => 'float',
        'returned_quantity' => 'float',
        'discount_amount' => 'float',
        'tax_rate' => 'float',
        'tax_amount' => 'float',
        'total_amount' => 'float',
        'metadata' => 'array',
    ];

    /**
     * Protect line prices under the Price-Lock Law if parent order is confirmed.
     */
    protected static function booted(): void
    {
        parent::booted();

        static::updating(function (self $line): void {
            if ($line->relationLoaded('order') || $line->sales_order_id) {
                $order = $line->order ?? SalesOrder::find($line->sales_order_id);
                if ($order) {
                    $orderStatus = $order->status instanceof OrderStatus
                        ? $order->status
                        : OrderStatus::tryFrom((string) $order->status);

                    if ($orderStatus && $orderStatus->isLocked()) {
                        $dirtyKeys = array_keys($line->getDirty());
                        $frozenKeys = ['unit_price', 'quantity', 'discount_amount', 'tax_rate', 'tax_amount', 'total_amount'];
                        $violated = array_intersect($dirtyKeys, $frozenKeys);

                        if (!empty($violated)) {
                            throw OrderPriceLockedException::forOrder($order->order_number, 'alter line pricing on');
                        }
                    }
                }
            }
        });

        static::deleting(function (self $line): void {
            $order = $line->order ?? SalesOrder::find($line->sales_order_id);
            if ($order) {
                $orderStatus = $order->status instanceof OrderStatus
                    ? $order->status
                    : OrderStatus::tryFrom((string) $order->status);

                if ($orderStatus && $orderStatus->isLocked()) {
                    throw OrderPriceLockedException::forOrder($order->order_number, 'delete lines from');
                }
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function orderable(): MorphTo
    {
        return $this->morphTo();
    }

    public function batchLines(): HasMany
    {
        return $this->hasMany(FulfillmentBatchLine::class, 'sales_order_line_id');
    }

    public function rmaLines(): HasMany
    {
        return $this->hasMany(RmaLine::class, 'sales_order_line_id');
    }

    public function unallocatedQuantity(): float
    {
        return max(0.0, (float) $this->quantity - (float) $this->allocated_quantity);
    }

    public function returnableQuantity(): float
    {
        return max(0.0, (float) $this->shipped_quantity - (float) $this->returned_quantity);
    }
}
