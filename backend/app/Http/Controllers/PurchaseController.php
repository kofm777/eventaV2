<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseTicketRequest;
use App\Models\Event;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PurchaseController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private PaymentService $paymentService
    ) {
    }

    /**
     * Guest starts a ticket purchase: creates a PENDING_PAYMENT order and a payment intent.
     */
    public function purchase(PurchaseTicketRequest $request, string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)->first();

        if (!$event || !$event->is_published || !$event->allow_guest_checkout) {
            return response()->json([
                'ok' => false,
                'message' => 'Guest checkout not available for this event.',
            ], 403);
        }

        try {
            $order = $this->orderService->createOrder($event, $request->validated());

            // FREE TICKETS (amount_total == 0) bypass EVERY payment driver and issue
            // immediately. The free branch keys off the server-computed amount_total
            // (from tier prices), NOT client input, so a paid tier can never slip
            // through free — and the path never touches a PaymentService, so no driver
            // can be tricked into issuing for an unpaid paid-tier order.
            // Compare on the rounded float (amount_total is a decimal:2 cast/string);
            // bccomp when available for exact 2dp comparison, float fallback otherwise.
            $isFree = function_exists('bccomp')
                ? bccomp((string) $order->amount_total, '0', 2) <= 0
                : round((float) $order->amount_total, 2) <= 0;

            if ($isFree) {
                $order = $this->orderService->markPaidAndIssueTicket($order, 'free');

                // Mark the driver as 'free' for reporting (issuance already stamped PAID).
                if ($order->payment_provider !== 'free') {
                    $order->update(['payment_provider' => 'free']);
                }

                return $this->ticketIssuedResponse($order, ['type' => 'issued']);
            }

            $intent = $this->paymentService->createIntent($order);

            return response()->json([
                'ok' => true,
                'order' => [
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'amount_total' => $order->amount_total,
                    'currency' => $order->currency,
                ],
                'payment' => [
                    'provider' => $intent->provider,
                    'intent_id' => $intent->intentId,
                    'client_action' => $intent->clientAction,
                ],
                'message' => 'Order created. Awaiting payment confirmation.',
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create purchase order', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Error while creating order.',
            ], 500);
        }
    }

    /**
     * Confirm payment via the bound PaymentService, then issue + email the ticket.
     *
     * Idempotent: returns the same payload if the order is already PAID, so a
     * double-return / refresh can't double-issue (markPaidAndIssueTicket also guards
     * under a row lock).
     *
     * Security: the request body carries gateway redirect data (e.g. {payment_id} on
     * the Flouci return). The bound driver decides paid/not-paid — stub auto-confirms
     * (demo), flouci re-verifies SERVER-SIDE via verify_payment and is paid ONLY when
     * Flouci reports result.status === 'SUCCESS'. The buyer cannot forge SUCCESS by
     * hitting this endpoint directly: confirm() triggers a fresh server-to-server
     * verify against Flouci using the payment_id WE generated for THIS order.
     */
    public function confirm(Request $request, string $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)->first();

        if (!$order) {
            return response()->json([
                'ok' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        try {
            if (!$order->isPaid()) {
                // The single line a real gateway changes is the confirm() impl below.
                // The request body (e.g. {payment_id}) is forwarded to the driver.
                $confirmation = $this->paymentService->confirm($order, $request->all());

                if (!$confirmation->paid) {
                    $order->update(['status' => Order::STATUS_FAILED]);

                    return response()->json([
                        'ok' => false,
                        'message' => $confirmation->failureReason ?? 'Payment failed.',
                    ], 402);
                }

                $order = $this->orderService->markPaidAndIssueTicket($order, $confirmation->reference);
            }

            return $this->ticketIssuedResponse($order);
        } catch (\Exception $e) {
            Log::error('Failed to confirm order', [
                'order_number' => $orderNumber,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Error while confirming payment.',
            ], 500);
        }
    }

    /**
     * Poll order status (for redirect-return gateways later).
     */
    public function show(string $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)->first();

        if (!$order) {
            return response()->json([
                'ok' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        $payload = [
            'order_number' => $order->order_number,
            'status' => $order->status,
            'amount_total' => $order->amount_total,
            'currency' => $order->currency,
        ];

        if ($order->isPaid()) {
            $payload['ticket_download_token'] = $order->ticket_download_token;
        }

        return response()->json([
            'ok' => true,
            'order' => $payload,
        ]);
    }

    /**
     * Shared PAID response with ticket download info.
     *
     * @param array<string, mixed>|null $clientAction Optional client_action hint, e.g.
     *                                   ['type'=>'issued'] for the free short-circuit.
     */
    private function ticketIssuedResponse(Order $order, ?array $clientAction = null): JsonResponse
    {
        $token = $order->ticket_download_token;
        $downloadUrl = rtrim(config('app.frontend_url'), '/') . '/ticket/' . $token;

        $payload = [
            'ok' => true,
            'order' => [
                'order_number' => $order->order_number,
                'status' => $order->status,
                'amount_total' => $order->amount_total,
                'currency' => $order->currency,
            ],
            'ticket' => [
                'download_token' => $token,
                'download_url' => $downloadUrl,
                'qr_image' => optional($order->participant)->qr_image,
            ],
            'message' => 'Ticket issued.',
        ];

        if ($clientAction !== null) {
            $payload['client_action'] = $clientAction;
        }

        return response()->json($payload, $clientAction !== null ? 201 : 200);
    }
}
