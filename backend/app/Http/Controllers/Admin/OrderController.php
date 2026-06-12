<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\RefundException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderRefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Organizer-scoped Orders list (NEW in Phase 1).
 *
 * Order already uses the Phase 0 BelongsToOrganizer trait, so Order::query() is
 * AUTO-SCOPED by the global scope to the authenticated organizer's rows (and is a
 * no-op for super-admin, who sees all orders). ZERO scoping logic lives here.
 */
class OrderController extends Controller
{
    public function __construct(
        private OrderRefundService $orderRefundService
    ) {
    }

    /**
     * List the org's orders (paginated). Optional ?status= and ?search= (order_number/buyer email/name).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('buyer_email', 'like', "%{$search}%")
                  ->orWhere('buyer_first_name', 'like', "%{$search}%")
                  ->orWhere('buyer_last_name', 'like', "%{$search}%");
            });
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'ok' => true,
            'orders' => $orders,
        ]);
    }

    /**
     * Refund a PAID order (organizer-scoped + super-admin).
     *
     * The Order lookup is AUTO-SCOPED by the Phase 0 BelongsToOrganizer global scope:
     * an owner/admin hitting another org's order_number gets NULL -> 404 (non-enumerable,
     * own-rows-only); super-admin's scope is a no-op so they can refund any org's order.
     *
     * Maps RefundException::status -> 422 (illegal state, e.g. not PAID) / 502 (gateway
     * refund failed; the order stays PAID, no half-state).
     */
    public function refund(Request $request, string $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)->first();

        if (! $order) {
            return response()->json([
                'ok' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        $manual = $request->boolean('manual');

        try {
            $order = $this->orderRefundService->refund(
                $order,
                $request->user('sanctum')?->id,
                $manual
            );
        } catch (RefundException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], $e->status);
        } catch (\Throwable $e) {
            Log::error('Failed to refund order', [
                'order_number' => $orderNumber,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Error while refunding the order.',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'order' => $order,
            'message' => 'Order refunded.',
        ]);
    }

    /**
     * Cancel an order (organizer-scoped + super-admin). Handles pre- and post-payment:
     * a PAID order is refunded, a PENDING order is cancelled, a FAILED order is rejected
     * (422). Auto-scoped + RefundException mapping identical to refund().
     */
    public function cancel(Request $request, string $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)->first();

        if (! $order) {
            return response()->json([
                'ok' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        $manual = $request->boolean('manual');

        try {
            $order = $this->orderRefundService->cancelOrder(
                $order,
                $request->user('sanctum')?->id,
                $manual
            );
        } catch (RefundException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], $e->status);
        } catch (\Throwable $e) {
            Log::error('Failed to cancel order', [
                'order_number' => $orderNumber,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Error while cancelling the order.',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'order' => $order,
            'message' => 'Order cancelled.',
        ]);
    }
}
