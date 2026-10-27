<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Enums;

/**
 * Lifecycle states for Return Merchandise Authorization (RMA).
 */
enum RmaStatus: string
{
    case Draft = 'draft';
    case PendingAuthorization = 'pending_authorization';
    case Authorized = 'authorized';
    case Received = 'received';
    case ApprovedForCredit = 'approved_for_credit';
    case Rejected = 'rejected';
    case Closed = 'closed';
}
