<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseTicketRequest;
use App\Models\Event;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
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
     * Confirm payment via PaymentService (stub auto-confirms), issue + email the ticket.
     * Idempotent: returns the same payload if the order is already PAID.
     */
    public function confirm(string $orderNumber): JsonResponse
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
                $confirmation = $this->paymentService->confirm($order);

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
     */
    private function ticketIssuedResponse(Order $order): JsonResponse
    {
        $token = $order->ticket_download_token;
        $downloadUrl = rtrim(config('app.frontend_url'), '/') . '/ticket/' . $token;

        return response()->json([
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
        ]);
    }
}
