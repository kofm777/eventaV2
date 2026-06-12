<?php

namespace App\Services;

use App\Exceptions\RefundException;
use App\Models\Order;
use App\Models\Ticket;
use App\Services\Payments\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Owns the refund + order-cancellation lifecycle (Refunds & Cancellations).
 *
 * Reuses every existing seam:
 *   - the Phase 5 Order state machine (transitionTo / canTransitionTo), now extended to
 *     allow PAID -> REFUNDED / CANCELLED;
 *   - the PaymentService::refund() gateway seam (Stub no-op success, Flouci manual TODO);
 *   - Ticket::isRevoked() which the Phase 2 ScanController already rejects, so voiding a
 *     ticket needs ZERO scanner change;
 *   - the WHERE status=PAID money aggregation, so a REFUNDED/CANCELLED order drops out of
 *     every ledger total automatically (captured platform_fee/organizer_amount are LEFT
 *     intact on the row for audit — they simply stop being summed).
 *
 * All public methods are idempotent, transactional, and row-locked (mirroring
 * OrderService::markPaidAndIssueTicket) so a raced double-refund can never double-charge
 * the gateway or double-void tickets.
 */
class OrderRefundService
{
    public function __construct(
        private PaymentService $paymentService
    ) {
    }

    /**
     * Refund a PAID order: gateway refund -> void tickets (REFUNDED) -> guarded
     * PAID->REFUNDED transition -> stamp refunded_at + refund_reference.
     *
     * Idempotent: an already-REFUNDED order returns unchanged (no gateway call). Only a
     * PAID order may be refunded; any other status is a 422.
     *
     * @param bool $manual When true (out-of-band / manual gateway refund, e.g. Flouci),
     *                     skips the PaymentService::refund() call and records
     *                     refund_reference='manual'. The ledger exclusion still happens.
     */
    public function refund(Order $order, ?int $actorId = null, bool $manual = false): Order
    {
        // Fast-path idempotency (before any txn/gateway): re-refunding a REFUNDED order
        // is a logged no-op — never a second gateway refund.
        if ($order->status === Order::STATUS_REFUNDED) {
            Log::info('Refund no-op: order already REFUNDED', [
                'order_number' => $order->order_number,
                'actor_id' => $actorId,
            ]);

            return $order;
        }

        if ($order->status !== Order::STATUS_PAID) {
            throw RefundException::illegalState('Only a paid order can be refunded.');
        }

        return DB::transaction(function () use ($order, $actorId, $manual) {
            // Re-read locked to serialize against a concurrent refund/confirm (lost-race
            // protection mirroring markPaidAndIssueTicket).
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->first();

            // A racer already refunded it between the fast-path and the lock: no-op.
            if ($locked->status === Order::STATUS_REFUNDED) {
                return $locked;
            }

            // Status changed under us to a non-PAID, non-REFUNDED terminal: refuse.
            if ($locked->status !== Order::STATUS_PAID) {
                throw RefundException::illegalState('Only a paid order can be refunded.');
            }

            // Gateway refund (skipped for an explicit manual/out-of-band refund).
            if ($manual) {
                $refundReference = 'manual';
            } else {
                $result = $this->paymentService->refund($locked);

                if (! $result->success) {
                    // Roll back the whole txn — order stays PAID, no half-state, no ledger
                    // change. Controller maps this to a 502.
                    throw RefundException::gatewayFailed(
                        $result->failureReason ?? 'Refund failed at the payment gateway.'
                    );
                }

                $refundReference = $result->refundId;
            }

            // Void all still-active tickets for this order (refunded => isRevoked()).
            $this->voidTickets($locked, Ticket::STATUS_REFUNDED);

            // Phase 5 guarded transition PAID -> REFUNDED.
            if (! $locked->transitionTo(Order::STATUS_REFUNDED)) {
                // Should be unreachable (we hold the lock and re-checked PAID), but never
                // leave a half-state: abort so the gateway refund + voids roll back.
                throw RefundException::illegalState('Order could not be transitioned to REFUNDED.');
            }

            $locked->refunded_at = now();
            $locked->refund_reference = $refundReference;
            $locked->save();

            Log::info('Order refunded', [
                'order_number' => $locked->order_number,
                'actor_id' => $actorId,
                'manual' => $manual,
                'refund_reference' => $refundReference,
            ]);

            return $locked->fresh();
        });
    }

    /**
     * Cancel an order, handling pre- AND post-payment:
     *   - CANCELLED / REFUNDED -> idempotent no-op.
     *   - PAID                 -> delegate to refund() (captured money cannot be
     *                             abandoned; final status REFUNDED).
     *   - PENDING_PAYMENT      -> clean cancel: void any tickets, guarded
     *                             PENDING->CANCELLED, stamp cancelled_at. No gateway call.
     *   - FAILED               -> 422 (terminal, nothing to cancel).
     */
    public function cancelOrder(Order $order, ?int $actorId = null, bool $manual = false): Order
    {
        if (in_array($order->status, [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED], true)) {
            return $order; // idempotent no-op
        }

        if ($order->status === Order::STATUS_PAID) {
            // Cannot abandon captured money: a paid cancel MUST run the refund path
            // (void + gateway + ledger drop). Stamp cancelled_at too for audit.
            $refunded = $this->refund($order, $actorId, $manual);
            if ($refunded->cancelled_at === null) {
                $refunded->cancelled_at = now();
                $refunded->save();
            }

            return $refunded->fresh();
        }

        if ($order->status === Order::STATUS_FAILED) {
            throw RefundException::illegalState('A failed order cannot be cancelled.');
        }

        // PENDING_PAYMENT (the only remaining branch): clean cancel.
        return DB::transaction(function () use ($order, $actorId) {
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->first();

            if (in_array($locked->status, [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED], true)) {
                return $locked;
            }

            if ($locked->status === Order::STATUS_PAID) {
                // Raced into PAID under the lock — fall back to the refund path outside
                // this txn would be cleaner, but we can refund inline safely here.
                throw RefundException::illegalState('Order became paid; refund it instead of cancelling.');
            }

            if ($locked->status === Order::STATUS_FAILED) {
                throw RefundException::illegalState('A failed order cannot be cancelled.');
            }

            // Void any tickets (normally none for a pending order).
            $this->voidTickets($locked, Ticket::STATUS_CANCELLED);

            // PENDING_PAYMENT -> CANCELLED is already legal in the Phase 5 machine.
            if (! $locked->transitionTo(Order::STATUS_CANCELLED)) {
                throw RefundException::illegalState('Order could not be transitioned to CANCELLED.');
            }

            $locked->cancelled_at = now();
            $locked->save();

            Log::info('Order cancelled', [
                'order_number' => $locked->order_number,
                'actor_id' => $actorId,
            ]);

            return $locked->fresh();
        });
    }

    /**
     * Void the owner's (Order or Event) still-active tickets, stamping voided_at.
     *
     * Selects only VALID/CHECKED_IN tickets so a re-run skips already-voided rows
     * (idempotent). Runs inside the caller's transaction under the row lock. A voided
     * ticket immediately fails the scanner via the unchanged Ticket::isRevoked() gate; a
     * voided ticket also stops counting against capacity (assertCapacity counts only
     * VALID/CHECKED_IN).
     *
     * @param Order|\App\Models\Event $owner      Must expose a tickets() relation.
     * @param string                  $voidStatus Ticket::STATUS_REFUNDED or STATUS_CANCELLED.
     *
     * @return int number of tickets voided
     */
    private function voidTickets($owner, string $voidStatus): int
    {
        return $owner->tickets()
            ->whereIn('status', [Ticket::STATUS_VALID, Ticket::STATUS_CHECKED_IN])
            ->update([
                'status' => $voidStatus,
                'voided_at' => now(),
            ]);
    }
}
