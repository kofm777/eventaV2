<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Auto-confirming payment stub.
 *
 * Implements the full real-gateway lifecycle (intent -> confirm -> webhook) so that
 * swapping in a real provider (Stripe / Paymee / Konnect / Flouci) only changes the
 * body of these methods, never the controllers or OrderService.
 *
 * INTENTIONALLY permissive (confirm() returns paid:true unconditionally) — that is
 * the demo affordance, fenced entirely behind PAYMENT_DRIVER=stub. Once a real
 * gateway is live, set PAYMENT_DRIVER=flouci and this is never the prod path.
 */
class StubPaymentService implements PaymentService
{
    /**
     * {@inheritDoc}
     */
    public function createIntent(Order $order): PaymentIntentResult
    {
        // Make misconfiguration loud: a stub createIntent in any environment means
        // NO real charge is being taken for this order.
        Log::warning('STUB payment driver active — NO real charge. Demo only.', [
            'order_number' => $order->order_number,
            'amount_total' => $order->amount_total,
        ]);

        // Optional hard guard: refuse the stub under APP_ENV=production unless the
        // owner explicitly opted in via ALLOW_STUB_PAYMENTS=true. Keeps a prod
        // misconfig from silently issuing tickets for unpaid orders.
        if (app()->environment('production')
            && ! (bool) config('services.payments.allow_stub_in_production')) {
            Log::error('STUB payment driver blocked in production. Set PAYMENT_DRIVER=flouci or ALLOW_STUB_PAYMENTS=true.', [
                'order_number' => $order->order_number,
            ]);

            throw new RuntimeException('Stub payment driver is disabled in production.');
        }

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

    /**
     * {@inheritDoc}
     *
     * STUB: no-op success. No real money moves (no real charge was taken), but we return
     * success:true with a synthetic refund id so the refund lifecycle (void tickets,
     * REFUNDED transition, ledger exclusion) runs end-to-end in the demo.
     */
    public function refund(Order $order, ?float $amount = null): PaymentRefundResult
    {
        Log::warning('STUB payment driver refund — NO real money moved. Demo only.', [
            'order_number' => $order->order_number,
            'amount' => $amount ?? $order->amount_total,
        ]);

        // TODO(real gateway): call $stripe->refunds->create(['payment_intent'=>...]) or the
        // provider's refund endpoint; map success/failure onto PaymentRefundResult.
        return new PaymentRefundResult(
            provider: 'stub',
            refundId: 'stub_refund_' . Str::random(16),
            success: true
        );
    }
}
