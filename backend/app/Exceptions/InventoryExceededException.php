<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown at issuance when a ticket_type's quantity_sold + requested seats would
 * exceed its quantity (sold out / not enough left). Surfaces as a 422.
 */
class InventoryExceededException extends RuntimeException
{
    public function __construct(string $message = 'Sold out / not enough tickets left.')
    {
        parent::__construct($message);
    }
}
