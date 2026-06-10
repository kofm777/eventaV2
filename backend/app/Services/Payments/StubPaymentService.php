<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Support\Str;

/**
 * Auto-confirming payment stub.
 *
 * Implements the full real-gateway lifecycle (intent -> confirm -> webhook) so that
 * swapping in a real provider (Stripe / Paymee / Konnect) only changes the body of
 * these methods, never the controllers or OrderService.
 */
class StubPaymentService implements PaymentService
{
    /**
     * {@inheritDoc}
     */
    public function createIntent(Order $order): PaymentIntentResult
    {
        $order->payment_provider = 'stub';
        $order->payment_intent_id = 'stub_' . Str::random(24);
        $order->save();

        // TODO(real gateway): call $stripe->paymentIntents->create([...]) or create a
        // Paymee/Konnect checkout session; return clientAction ['type'=>'redirect','url'=>$session->url].
        return new PaymentIntentResult(
            provider: 'stub',
            intentId: $order->payment_intent_id,
            clientAction: ['type' => 'auto_confirm']
        );
    }

    /**
     * {@inheritDoc}
     *
     * STUB auto-confirms unconditionally.
     */
    public function confirm(Order $order, array $payload = []): PaymentConfirmation
    {
        $order->payment_reference = 'stub_ref_' . Str::random(16);

        // TODO(real gateway): retrieve the intent by $order->payment_intent_id, check
        // status === 'succeeded'; only then paid:true. Do NOT trust the client.
        return new PaymentConfirmation(
            order: $order,
            paid: true,
            reference: $order->payment_reference
        );
    }

    /**
     * {@inheritDoc}
     *
     * STUB: find order by payload['order_number'] or payload['payment_intent_id'],
     * return confirmation paid:true.
     */
    public function handleWebhook(array $payload, array $headers): ?PaymentConfirmation
    {
        // TODO(real gateway): verify the provider signature using $headers
        // (e.g. Stripe-Signature) BEFORE trusting payload; map provider event type
        // (payment_intent.succeeded -> paid:true, payment_failed -> paid:false).

        $order = null;

        if (!empty($payload['order_number'])) {
            $order = Order::where('order_number', $payload['order_number'])->first();
        }

        if (!$order && !empty($payload['payment_intent_id'])) {
            $order = Order::where('payment_intent_id', $payload['payment_intent_id'])->first();
        }

        if (!$order) {
            return null;
        }

        $reference = $order->payment_reference ?: 'stub_ref_' . Str::random(16);

        return new PaymentConfirmation(
            order: $order,
            paid: true,
            reference: $reference
        );
    }
}
