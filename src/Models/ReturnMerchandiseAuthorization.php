<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Obelaw\Ium\Sales\Base\ModelBase;
use Obelaw\Ium\Sales\Enums\RmaStatus;

/**
 * Return Merchandise Authorization (RMA) Aggregate Root.
 *
 * Automatically maps to table `ium_sales_rmas`.
 */
class ReturnMerchandiseAuthorization extends ModelBase
{
    protected $table = 'rmas';

    protected $fillable = [
        'rma_number',
        'sales_order_id',
        'customer_type',
        'customer_id',
        'status',
        'reason_code',
        'reason_description',
        'return_type',
        'authorized_at',
        'received_at',
        'closed_at',
        'metadata',
    ];

    protected $casts = [
        'status' => RmaStatus::class,
        'authorized_at' => 'datetime',
        'received_at' => 'datetime',
        'closed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function customer(): MorphTo
    {
        return $this->morphTo();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RmaLine::class, 'rma_id');
    }
}
