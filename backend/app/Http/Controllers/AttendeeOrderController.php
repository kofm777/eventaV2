<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PHASE 6 — the logged-in attendee's "My Tickets" wallet.
 *
 * Read-only; issues no tokens and changes no state. The ISOLATION boundary is the
 * EXPLICIT `where('attendee_id', $me->id)` — NOT any tenant/token magic. Because Order
 * uses BelongsToOrganizer whose global scope is a no-op for non-admin (guest-like)
 * context, the attendee correctly sees their orders across ALL organizers.
 *
 * For each PAID order it surfaces the EXISTING `ticket_download_token` (the same
 * no-login credential TicketController already serves), so the wallet links straight to
 * /ticket/{token} — reusing all existing QR/PDF/badge plumbing, with no new token type
 * and no magic-link email required when logged in.
 */
class AttendeeOrderController extends Controller
{
    public function myTickets(Request $request): JsonResponse
    {
        $attendee = $request->user('sanctum-attendee');

        $orders = Order::where('attendee_id', $attendee->id)
            ->with('event:id,name,slug,starts_at')
            ->latest()
            ->get();

        $payload = $orders->map(function (Order $order) {
            $row = [
                'order_number' => $order->order_number,
                'event' => $order->event ? [
                    'name' => $order->event->name,
                    'slug' => $order->event->slug,
                    'starts_at' => $order->event->starts_at,
                ] : null,
                'status' => $order->status,
                'amount_total' => $order->amount_total,
                'currency' => $order->currency,
                'created_at' => $order->created_at,
                // PAID orders carry the no-login download token; PENDING/FAILED don't.
                'ticket_download_token' => $order->isPaid() ? $order->ticket_download_token : null,
            ];

            return $row;
        });

        return response()->json([
            'ok' => true,
            'orders' => $payload,
        ]);
    }
}
