<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Exceptions;

use DomainException;

/**
 * Thrown when an invalid state transition is requested.
 */
class InvalidOrderStateTransitionException extends DomainException
{
    public static function transition(string $entity, string $fromState, string $toState): self
    {
        return new self("Invalid {$entity} state transition: cannot transition from [{$fromState}] to [{$toState}].");
    }
}
