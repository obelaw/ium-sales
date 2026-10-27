<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Services;

use Illuminate\Database\Eloquent\Model;
use Obelaw\Ium\Sales\Enums\SalesConfigEnum;
use Obelaw\Ium\Sales\Models\SalesAuditLog;

/**
 * Append-only immutable forensic commercial audit logger.
 */
final class AuditService
{
    /**
     * Log a state transition, price change, or commercial event.
     *
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function log(
        Model $auditable,
        string $action,
        ?string $originChannel = null,
        ?array $before = null,
        ?array $after = null,
        ?string $reasonCode = null,
        ?string $actorId = null,
        ?string $actorType = 'system'
    ): ?SalesAuditLog {
        if (!ium_config(SalesConfigEnum::AuditEnabled, true)) {
            return null;
        }

        return SalesAuditLog::create([
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'action' => $action,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'origin_channel' => $originChannel,
            'before_payload' => $before,
            'after_payload' => $after,
            'reason_code' => $reasonCode,
            'created_at' => now(),
        ]);
    }
}
