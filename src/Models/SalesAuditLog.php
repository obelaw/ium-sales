<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Obelaw\Ium\Sales\Base\ModelBase;

/**
 * Append-only immutable commercial forensic audit log.
 *
 * Automatically maps to table `ium_sales_audit_logs`.
 */
class SalesAuditLog extends ModelBase
{
    public const UPDATED_AT = null;

    protected $table = 'audit_logs';

    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'action',
        'actor_type',
        'actor_id',
        'origin_channel',
        'before_payload',
        'after_payload',
        'reason_code',
        'created_at',
    ];

    protected $casts = [
        'before_payload' => 'array',
        'after_payload' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Audit logs are strictly append-only: updates and deletes are forbidden.
     */
    protected static function booted(): void
    {
        parent::booted();

        static::updating(function (): void {
            throw new \LogicException('Audit trail records are strictly immutable and cannot be modified.');
        });

        static::deleting(function (): void {
            throw new \LogicException('Audit trail records cannot be deleted.');
        });
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
