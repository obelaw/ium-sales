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
 * Sales Order (SO) Aggregate Root.
 *
 * Enforces the Price-Lock Law: once confirmed, commercial terms and line prices
 * are frozen permanently and cannot be modified or destroyed in place.
 *
 * Automatically maps to table `ium_sales_orders`.
 */
class SalesOrder extends ModelBase
{
    protected $table = 'orders';

    protected $fillable = [
        'order_number',
        'customer_type',
        'customer_id',
        'channel_code',
        'currency',
        'status',
        'payment_status',
        'payment_terms',
        'billing_address',
        'shipping_address',
        'subtotal',
        'discount_total',
        'tax_total',
        'shipping_total',
        'grand_total',
        'confirmed_at',
        'cancelled_at',
        'cancellation_reason',
        'quotation_id',
        'metadata',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'subtotal' => 'float',
        'discount_total' => 'float',
        'tax_total' => 'float',
        'shipping_total' => 'float',
        'grand_total' => 'float',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Enforce Domain Invariant 2: "The Price-Lock Law"
     * Once confirmed, commercial terms, totals, and currency can NEVER be mutated.
     */
    protected static function booted(): void
    {
        parent::booted();

        static::updating(function (self $order): void {
            $originalStatus = $order->getOriginal('status');
            $originalStatusEnum = $originalStatus instanceof OrderStatus
                ? $originalStatus
                : OrderStatus::tryFrom((string) $originalStatus);

            if ($originalStatusEnum && $originalStatusEnum->isLocked()) {
                $dirtyKeys = array_keys($order->getDirty());

                // Fields strictly protected under the Price-Lock Law
                $frozenCommercialKeys = [
                    'currency',
                    'subtotal',
                    'discount_total',
                    'tax_total',
                    'shipping_total',
                    'grand_total',
                ];

                $violatedKeys = array_intersect($dirtyKeys, $frozenCommercialKeys);

                if (!empty($violatedKeys)) {
                    throw OrderPriceLockedException::forOrder($order->order_number, 'update prices of');
                }
            }
        });

        static::deleting(function (self $order): void {
            $status = $order->status instanceof OrderStatus
                ? $order->status
                : OrderStatus::tryFrom((string) $order->status);

            if ($status && $status->isLocked()) {
                throw OrderPriceLockedException::forOrder($order->order_number, 'delete confirmed');
            }
        });
    }

    public function customer(): MorphTo
    {
        return $this->morphTo();
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class, 'sales_order_id')->orderBy('line_number');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(FulfillmentBatch::class, 'sales_order_id');
    }

    public function rmas(): HasMany
    {
        return $this->hasMany(ReturnMerchandiseAuthorization::class, 'sales_order_id');
    }
}
