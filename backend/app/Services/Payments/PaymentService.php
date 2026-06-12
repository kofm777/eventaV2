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

/**
 * Result of refunding a captured payment.
 *
 * success:false leaves the order PAID (no ledger change) and the controller maps it to
 * a 502 so money is never recorded as moved out when the gateway did not actually refund.
 * The Stub always succeeds (no-op demo); Flouci returns success:false until its refund
 * API is integrated (manual refunds use the explicit manual override path instead).
 */
class PaymentRefundResult
{
    public function __construct(
        public readonly string $provider,
        public readonly string $refundId,
        public readonly bool $success,
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

    /**
     * Refund a captured payment for the order.
     *
     * @param Order      $order  The PAID order being refunded.
     * @param float|null $amount Optional partial-refund amount; null = full refund.
     *
     * @return PaymentRefundResult success:true => gateway refunded (ledger excludes it);
     *         success:false => gateway did NOT refund, order stays PAID (controller 502).
     */
    public function refund(Order $order, ?float $amount = null): PaymentRefundResult;
}
