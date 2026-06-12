<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by OrderRefundService / EventCancellationService when a refund or cancellation
 * cannot proceed. Carries the intended HTTP status so the admin controllers can map it
 * directly (422 for an illegal state, 502 for a payment-gateway refund failure).
 */
class RefundException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }

    /**
     * The order is not in a state that allows the requested action (422).
     */
    public static function illegalState(string $message): self
    {
        return new self($message, 422);
    }

    /**
     * The payment gateway refused/failed the refund — order stays PAID (502).
     */
    public static function gatewayFailed(string $message): self
    {
        return new self($message, 502);
    }
}
