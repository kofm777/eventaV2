<?php

namespace App\Http\Controllers;

use App\Services\OrderService;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Payment gateway webhook (STUB).
 *
 * The real gateway redirects the buyer, then POSTs a provider event here. The
 * webhook verifies the signature (TODO in the PaymentService impl), resolves the
 * order, and — on a paid result — issues the ticket via OrderService. The business
 * transition lives in OrderService so swapping the gateway never touches this controller.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private OrderService $orderService
    ) {
    }

    /**
     * Handle an inbound provider webhook. Always returns 200 on a handled event
     * so the gateway does not retry; 404 only when the order cannot be matched.
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            // TODO(real gateway): pass raw headers so handleWebhook() can verify the
            // provider signature (e.g. Stripe-Signature) BEFORE trusting the payload.
            $confirmation = $this->paymentService->handleWebhook(
                $request->all(),
                $request->headers->all()
            );

            if (!$confirmation) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Order not matched.',
                ], 404);
            }

            if ($confirmation->paid) {
                $this->orderService->markPaidAndIssueTicket(
                    $confirmation->order,
                    $confirmation->reference
                );
            } else {
                // Phase 5 state guard: only a still-PENDING_PAYMENT order may move to
                // FAILED — never overwrite a terminal (PAID/REFUNDED/...) order.
                $confirmation->order->transitionTo(\App\Models\Order::STATUS_FAILED);
            }

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            Log::error('Payment webhook handling failed', [
                'error' => $e->getMessage(),
            ]);

            // Return 200 with ok:false so a noisy gateway does not hammer retries,
            // while still signalling the failure to observability.
            return response()->json([
                'ok' => false,
                'message' => 'Webhook processing error.',
            ], 200);
        }
    }
}
