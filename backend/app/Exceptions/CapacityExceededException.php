<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown at issuance when issuing the requested seats would exceed the event's
 * capacity (summed across all tiers). Surfaces as a 422.
 */
class CapacityExceededException extends RuntimeException
{
    public function __construct(string $message = 'Event at capacity.')
    {
        parent::__construct($message);
    }
}
