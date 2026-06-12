<?php

namespace App\Http\Controllers;

use App\Http\Requests\FindTicketRequest;
use App\Mail\TicketRetrievalMail;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * PHASE 4 — attendee find-my-tickets (POST /api/v1/tickets/find). Fixes audit H9.
 *
 * Throttled (3 / 10min, KEYED BY EMAIL via the named 'find-tickets' limiter),
 * email-keyed, magic-link, NON-ENUMERABLE.
 *
 * CRITICAL: ALWAYS returns the SAME generic ok:true response — whether 0 or N orders
 * matched — so the endpoint never reveals whether an email exists. Internally it looks
 * up the buyer's own PAID orders (orders.buyer_email is indexed; the email + the
 * unguessable download_token are the credential, consistent with TicketController's
 * token-as-credential model) and re-sends the EXISTING ticket_download_token link(s)
 * by email. It issues NO new tokens and changes NO order state (read-only + email).
 *
 * Public => the BelongsToOrganizer global scope is a no-op, so this correctly matches
 * across all of the buyer's orders without any scope bypass. Mail send is wrapped in
 * try/catch + Log::warning (like OrderService) so a mail failure never changes the
 * response.
 */
class TicketRetrievalController extends Controller
{
    /** The generic, always-identical response message (no existence leak). */
    private const GENERIC_MESSAGE = 'If a matching purchase exists, we just emailed your ticket link(s).';

    public function send(FindTicketRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $email = $validated['email'];
        $orderNumber = $validated['order_number'] ?? null;

        // Cheap, indexed lookup done either way to keep timing uniform.
        $orders = Order::where('buyer_email', $email)
            ->where('status', Order::STATUS_PAID)
            ->when($orderNumber, fn ($q) => $q->where('order_number', $orderNumber))
            ->whereNotNull('ticket_download_token')
            ->with('event:id,name')
            ->get();

        if ($orders->isNotEmpty()) {
            $links = $orders->map(fn (Order $order) => [
                'order_number' => $order->order_number,
                'event_name' => $order->event?->name,
                // Reuse the EXACT no-login link TicketController/OrderService already issue.
                'url' => rtrim(config('app.frontend_url'), '/') . '/ticket/' . $order->ticket_download_token,
            ])->values()->all();

            try {
                Mail::to($email)->send(new TicketRetrievalMail($links));
            } catch (\Exception $e) {
                // A mail failure must NEVER change the response (no enumeration via errors).
                Log::warning('Failed to send find-my-tickets email', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // ALWAYS identical — never reveals whether any order matched.
        return response()->json([
            'ok' => true,
            'message' => self::GENERIC_MESSAGE,
        ]);
    }
}
