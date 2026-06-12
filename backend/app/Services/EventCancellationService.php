<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cancels an event and cascades the cancellation across its orders + tickets
 * (Refunds & Cancellations).
 *
 * One-way by contract: once cancelled, orders are refunded/cancelled and tickets voided.
 * Un-cancelling is intentionally NOT auto-reversible (it would leave attendees refunded
 * but holding voided tickets and would double-issue on re-purchase); a future "uncancel"
 * must be a separate explicit action that only re-shows the event.
 *
 * Idempotent + transactional: re-running on an already-cancelled event is a no-op, and
 * because the per-order cascade reuses the idempotent OrderRefundService, the whole
 * cascade is itself safely re-runnable.
 */
class EventCancellationService
{
    public function __construct(
        private OrderRefundService $orderRefundService
    ) {
    }

    /**
     * Cancel the event: mark STATUS_CANCELLED, refund/cancel every non-terminal order,
     * and void all remaining active tickets.
     *
     * @param bool $manual Forwarded to OrderRefundService for out-of-band gateway refunds
     *                     (e.g. Flouci): records refund_reference='manual' and skips the
     *                     gateway call so the cascade still completes the ledger exclusion.
     */
    public function cancel(Event $event, ?int $actorId = null, bool $manual = false): Event
    {
        // Idempotency: already cancelled -> no-op.
        if ($event->isCancelled()) {
            return $event;
        }

        DB::transaction(function () use ($event, $actorId, $manual) {
            // 1. Mark the event cancelled (hides it from /discover + storefront, blocks
            //    new purchases in PurchaseController).
            $event->status = Event::STATUS_CANCELLED;
            $event->cancelled_at = now();
            $event->save();

            // 2. Cascade over every non-terminal order. PAID orders are refunded (gateway
            //    + void + ledger drop), PENDING orders are cancelled. Reuses the exact
            //    idempotent per-order logic so the cascade is re-runnable. Chunked to keep
            //    memory flat on large events.
            $event->orders()
                ->whereIn('status', [Order::STATUS_PENDING_PAYMENT, Order::STATUS_PAID])
                ->orderBy('id')
                ->chunkById(100, function ($orders) use ($actorId, $manual) {
                    foreach ($orders as $order) {
                        $this->orderRefundService->cancelOrder($order, $actorId, $manual);
                    }
                });

            // 3. Belt-and-suspenders: void ALL remaining active tickets for the event so
            //    order-less / legacy free tickets are revoked too. Idempotent (only touches
            //    VALID/CHECKED_IN rows; per-order voids already moved the rest).
            $event->tickets()
                ->whereIn('status', [Ticket::STATUS_VALID, Ticket::STATUS_CHECKED_IN])
                ->update([
                    'status' => Ticket::STATUS_CANCELLED,
                    'voided_at' => now(),
                ]);

            Log::info('Event cancelled', [
                'event_id' => $event->id,
                'slug' => $event->slug,
                'actor_id' => $actorId,
            ]);
        });

        return $event->fresh();
    }
}
