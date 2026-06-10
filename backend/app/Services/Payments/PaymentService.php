<?php

namespace App\Services\Payments;

use App\Models\Order;

/**
 * Result of creating a payment intent/session for an order.
 *
 * clientAction tells the frontend what to do next, e.g.
 *   ['type' => 'auto_confirm']            (stub)
 *   ['type' => 'redirect', 'url' => ...]  (real gateway)
 */
class PaymentIntentResult
{
    public function __construct(
        public readonly string $provider,
        public readonly string $intentId,
        public readonly array $clientAction
    ) {
    }
}

/**
 * Result of confirming/capturing a payment, or of an inbound webhook.
 */
class PaymentConfirmation
{
    public function __construct(
        public readonly Order $order,
        public readonly bool $paid,
        public readonly string $reference = '',
        public readonly ?string $failureReason = null
    ) {
    }
}

interface PaymentService
{
    /**
     * Create a payment intent/session for the order.
     * Returns the action the client must take next.
     */
    public function createIntent(Order $order): PaymentIntentResult;

    /**
     * Confirm/capture a payment.
     *
     * @param array $payload Gateway webhook/redirect data (empty for stub).
     */
    public function confirm(Order $order, array $payload = []): PaymentConfirmation;

    /**
     * Verify an inbound webhook (signature check) and resolve the matched order.
     *
     * @param array $payload Raw provider payload.
     * @param array $headers Inbound request headers (for signature verification).
     *
     * @return PaymentConfirmation|null Confirmation for the matched order, or null if unmatched.
     */
    public function handleWebhook(array $payload, array $headers): ?PaymentConfirmation;
}
