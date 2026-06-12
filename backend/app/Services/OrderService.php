<?php

namespace App\Services;

use App\Exceptions\CapacityExceededException;
use App\Exceptions\InventoryExceededException;
use App\Mail\ParticipantAccessMail;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organizer;
use App\Models\Participant;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Owns the order + ticket-issuance business lifecycle so a payment-gateway swap
 * never touches it.
 *
 * Phase 2: issuance is centralized here. Both the free /register flow and the
 * (still-disabled, Phase 3) paid purchase flow create order_items -> N ticket rows
 * via a shared issuer, under a DB row lock that enforces ticket_type inventory +
 * event capacity (H8). One ticket per seat, each with its own revocable expiring
 * HMAC QR (H7/H5/H6).
 */
class OrderService
{
    public function __construct(
        private QrCodeService $qrCodeService
    ) {
    }

    /**
     * Create a PENDING_PAYMENT order for the given event.
     *
     * Accepts EITHER:
     *  (a) the new items[] shape: $buyer['items'] = [['ticket_type_id'=>, 'quantity'=>], ...]; or
     *  (b) the legacy single-buyer shape (access_type + quantity), which is mapped to ONE
     *      line against the matching/default tier — so PurchaseTicketRequest needs no change.
     *
     * Validates each tier belongs to the event+organizer, is_active, inside its sales
     * window, and respects max_per_order; sums line_total -> amount_total.
     *
     * @param array<string, mixed> $buyer first_name,last_name,company_name,gender,phone,email,access_type,quantity[,items]
     */
    public function createOrder(Event $event, array $buyer): Order
    {
        $lines = $this->resolveOrderLines($event, $buyer);

        $amountTotal = round(
            array_reduce($lines, fn ($carry, $line) => $carry + $line['line_total'], 0.0),
            2
        );

        // Keep the legacy scalar columns populated for backward read (quantity = total
        // seats; access_type = first line's tier access_tier). amount_total now comes
        // from the summed lines, replacing event.ticket_price * quantity.
        $totalQuantity = array_reduce($lines, fn ($carry, $line) => $carry + $line['quantity'], 0);
        $primaryAccess = $lines[0]['access_tier'] ?? trim((string) ($buyer['access_type'] ?? 'fair'));

        return DB::transaction(function () use ($event, $buyer, $lines, $amountTotal, $totalQuantity, $primaryAccess) {
            $order = Order::create([
                // Guest purchase is unauthenticated (no HTTP organizer context), so derive
                // the tenant from the event explicitly instead of the auto-stamp hook.
                'organizer_id' => $event->organizer_id,
                'order_number' => $this->generateOrderNumber(),
                'event_id' => $event->id,
                // Phase 6: stamp the logged-in attendee (set EXPLICITLY from the token by
                // PurchaseController, like organizer_id from the event). NULL for guests —
                // Attendee has nothing to do with the BelongsToOrganizer tenant scope.
                'attendee_id' => $buyer['attendee_id'] ?? null,
                'buyer_email' => $buyer['email'],
                'buyer_first_name' => $buyer['first_name'],
                'buyer_last_name' => $buyer['last_name'],
                'buyer_company_name' => $buyer['company_name'] ?? null,
                'buyer_phone' => $buyer['phone'] ?? null,
                'gender' => $buyer['gender'],
                'access_type' => $primaryAccess,
                'quantity' => max(1, $totalQuantity),
                'amount_total' => $amountTotal,
                'currency' => $event->currency,
                'status' => Order::STATUS_PENDING_PAYMENT,
                // Stamp the provisional driver from config so flouci orders are
                // tagged correctly at creation. createIntent() re-stamps definitively
                // (e.g. 'flouci'); the free branch overrides to 'free' on issuance.
                'payment_provider' => (string) config('services.payments.driver', 'stub'),
            ]);

            foreach ($lines as $line) {
                OrderItem::create([
                    'organizer_id' => $event->organizer_id,
                    'order_id' => $order->id,
                    'ticket_type_id' => $line['ticket_type_id'],
                    'event_id' => $event->id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'line_total' => $line['line_total'],
                    'currency' => $event->currency,
                ]);
            }

            return $order;
        });
    }

    /**
     * Transition the order to PAID and issue ONE ticket per billed seat (H7).
     *
     * Idempotent: safe to call twice (confirm + webhook). Short-circuits if the order
     * is already PAID, already has a participant, or already has tickets.
     */
    public function markPaidAndIssueTicket(Order $order, string $reference = ''): Order
    {
        // Fast-path idempotency guard (before the transaction).
        if ($order->status === Order::STATUS_PAID || $order->participant_id || $order->tickets()->exists()) {
            return $order;
        }

        return DB::transaction(function () use ($order, $reference) {
            // Re-read with a lock to avoid a double-issue race (confirm + webhook).
            $order = Order::whereKey($order->getKey())->lockForUpdate()->first();

            if ($order->status === Order::STATUS_PAID || $order->participant_id || $order->tickets()->exists()) {
                return $order;
            }

            // Phase 5 state guard: only a still-PENDING_PAYMENT order may be issued/PAID.
            // A terminal order (REFUNDED/FAILED/CANCELLED) must never be re-issued. The
            // PAID case is already short-circuited above; this protects the others.
            if ($order->status !== Order::STATUS_PENDING_PAYMENT) {
                Log::warning('Refused to issue tickets for a non-pending order', [
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                ]);

                return $order;
            }

            $event = Event::find($order->event_id);

            // Issue N tickets across the order's items (one ticket per seat).
            $tickets = $this->issueTicketsForOrder($order, $event);

            $firstTicket = $tickets[0] ?? null;
            $primaryParticipant = $firstTicket ? $firstTicket->participant : null;

            // Capture platform commission at the single atomic moment the order
            // becomes PAID — so EVERY paid path (free, stub, flouci) records it once.
            // Phase 5: source the rate from the order's organizer when it has a
            // per-org commission_rate, otherwise fall back to the platform default
            // config('services.payments.commission_rate'). When commission_rate is NULL
            // the value is byte-for-byte the Phase 3 config rate, so existing PAID-order
            // math is unchanged. Free orders naturally get platform_fee=0 /
            // organizer_amount=0 since amount_total=0. These are pure CAPTURE columns.
            $organizer = $order->organizer_id ? Organizer::find($order->organizer_id) : null;
            $rate = $organizer
                ? $organizer->effectiveCommissionRate()
                : (float) config('services.payments.commission_rate', 0);
            $amountTotal = (float) $order->amount_total;
            $platformFee = round($amountTotal * $rate, 2);
            $organizerAmount = round($amountTotal - $platformFee, 2);

            // Mark order PAID; point participant_id + ticket_download_token at the first
            // issued seat so the existing TicketController + email link keep resolving.
            $order->participant_id = $primaryParticipant?->id;
            $order->status = Order::STATUS_PAID;
            $order->paid_at = now();
            $order->platform_fee = $platformFee;
            $order->organizer_amount = $organizerAmount;
            $order->payment_reference = $reference !== ''
                ? $reference
                : ($order->payment_reference ?: 'stub_ref_' . Str::random(16));
            $order->ticket_download_token = $firstTicket?->download_token ?: $this->generateOrderDownloadToken();
            $order->save();

            // Email the PRIMARY attendee (REUSE existing mailable) with the no-login link.
            if ($primaryParticipant && $firstTicket) {
                $ticketUrl = rtrim(config('app.frontend_url'), '/') . '/ticket/' . $order->ticket_download_token;

                try {
                    Mail::to($order->buyer_email)->send(
                        new ParticipantAccessMail($primaryParticipant, $firstTicket->qr_image, 'default', $ticketUrl)
                    );
                } catch (\Exception $e) {
                    Log::warning('Failed to send ticket email', [
                        'order_number' => $order->order_number,
                        'email' => $order->buyer_email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $order;
        });
    }

    /**
     * FREE /register entry point. Resolves the event's default free tier and issues ONE
     * ticket (order_id NULL) under the inventory/capacity lock, mirroring qr_token/qr_image
     * onto the participant for full backward-compat.
     *
     * When $event is null (legacy Demo registration with no event) it falls back to a
     * legacy ticket with event_id NULL and a participant-only QR so nothing 500s.
     *
     * @return Ticket|null the issued ticket (or null if no ticket could be created)
     */
    public function issueFreeTicket(?Event $event, Participant $participant, string $accessType): ?Ticket
    {
        $accessType = trim($accessType) !== '' ? trim($accessType) : 'fair';

        // No event (legacy Demo registration): mint a legacy bridge ticket with event_id
        // NULL, no tier, no capacity gate, reusing the participant's already-written QR.
        if (! $event) {
            return $this->issueLegacyParticipantTicket($participant, $accessType);
        }

        $ticketType = $this->resolveDefaultFreeTicketType($event, $accessType);

        return DB::transaction(function () use ($event, $participant, $accessType, $ticketType) {
            // Lock the tier row (if any) so concurrent issuances serialize on inventory.
            $lockedType = null;
            if ($ticketType) {
                $lockedType = TicketType::whereKey($ticketType->id)->lockForUpdate()->first();
                $this->assertInventory($lockedType, 1);
            }

            $this->assertCapacity($event, 1);

            $tier = $lockedType ?? $ticketType;

            $ticket = $this->createTicket(
                event: $event,
                order: null,
                orderItem: null,
                ticketType: $tier,
                participant: $participant,
                accessTier: $tier?->access_tier ?? $accessType,
                attendee: [
                    'first_name' => $participant->first_name,
                    'last_name' => $participant->last_name,
                    'email' => $participant->email,
                    'company_name' => $participant->company_name,
                    'phone' => $participant->phone,
                ]
            );

            // Mirror the per-seat QR onto the participant so existing badge/email/scan-by
            // -participant paths stay byte-for-byte unchanged (the /register response shape).
            $this->mirrorQrOntoParticipant($participant, $ticket);

            if ($lockedType) {
                $lockedType->increment('quantity_sold', 1);
            }

            return $ticket;
        });
    }

    /**
     * Shared issuer: create order_items' tickets — one per seat — under the lock.
     * Reuses/creates ONE participant per distinct attendee email (no overwrite race, H5).
     *
     * @return array<int, Ticket>
     */
    private function issueTicketsForOrder(Order $order, ?Event $event): array
    {
        $issued = [];

        // Snapshot the buyer as the default attendee for every seat (Phase 2 has no
        // per-seat attendee input yet; defaults copied from buyer).
        $buyerAttendee = [
            'first_name' => $order->buyer_first_name,
            'last_name' => $order->buyer_last_name,
            'email' => $order->buyer_email,
            'company_name' => $order->buyer_company_name,
            'phone' => $order->buyer_phone,
        ];

        // ONE participant per distinct attendee email (reuse existing, refresh to accepted).
        $participantCache = [];
        // Track which participant emails have had their QR mirrored (first seat only).
        $mirrored = [];

        $items = $order->orderItems()->get();

        foreach ($items as $item) {
            $tier = $item->ticket_type_id ? TicketType::whereKey($item->ticket_type_id)->lockForUpdate()->first() : null;
            $seats = max(1, (int) $item->quantity);

            if ($tier) {
                $this->assertInventory($tier, $seats);
            }

            if ($event) {
                $this->assertCapacity($event, $seats);
            }

            $accessTier = $tier?->access_tier ?? $order->access_type ?? 'fair';

            for ($i = 0; $i < $seats; $i++) {
                $attendee = $buyerAttendee;
                $emailKey = strtolower((string) $attendee['email']);

                $participant = $participantCache[$emailKey]
                    ?? ($participantCache[$emailKey] = $this->resolveParticipant($order, $event, $attendee, $accessTier));

                $ticket = $this->createTicket(
                    event: $event,
                    order: $order,
                    orderItem: $item,
                    ticketType: $tier,
                    participant: $participant,
                    accessTier: $accessTier,
                    attendee: $attendee
                );

                // Mirror the FIRST seat's QR onto its participant for backward badge/email read.
                if (! isset($mirrored[$emailKey])) {
                    $this->mirrorQrOntoParticipant($participant, $ticket);
                    $mirrored[$emailKey] = true;
                }

                $issued[] = $ticket;
            }

            if ($tier) {
                $tier->increment('quantity_sold', $seats);
            }
        }

        return $issued;
    }

    /**
     * Create a single ticket row + its per-seat v2 QR (jti, exp). The ticket_code is the
     * jti; expires_at falls back to the event's ends_at when present.
     */
    private function createTicket(
        ?Event $event,
        ?Order $order,
        ?OrderItem $orderItem,
        ?TicketType $ticketType,
        ?Participant $participant,
        string $accessTier,
        array $attendee
    ): Ticket {
        $ticketCode = $this->generateTicketCode();
        $organizerId = $event?->organizer_id ?? $order?->organizer_id ?? $participant?->organizer_id;

        $expiresAt = $event?->ends_at;

        $ticket = new Ticket([
            'organizer_id' => $organizerId,
            'event_id' => $event?->id,
            'order_id' => $order?->id,
            'order_item_id' => $orderItem?->id,
            'ticket_type_id' => $ticketType?->id,
            'participant_id' => $participant?->id,
            // Phase 6: propagate the order's owning attendee onto each issued seat so the
            // wallet can query tickets directly. NULL for the free /register + legacy
            // (order-less) paths and for every guest order.
            'attendee_id' => $order?->attendee_id,
            'attendee_first_name' => $attendee['first_name'] ?? null,
            'attendee_last_name' => $attendee['last_name'] ?? null,
            'attendee_email' => $attendee['email'] ?? null,
            'attendee_company_name' => $attendee['company_name'] ?? null,
            'attendee_phone' => $attendee['phone'] ?? null,
            'access_tier' => $accessTier,
            'ticket_code' => $ticketCode,
            'status' => Ticket::STATUS_VALID,
            'download_token' => $this->generateTicketDownloadToken(),
            'expires_at' => $expiresAt,
        ]);
        $ticket->save();

        // Build the v2 per-seat payload and sign it via the unchanged QrCodeService.
        $payload = [
            'v' => 2,
            'tid' => $ticket->id,
            'jti' => $ticketCode,
            'eid' => $event?->id,
            'oid' => $organizerId,
            'tier' => $accessTier,
            'exp' => $expiresAt ? $expiresAt->getTimestamp() : null,
        ];

        $qrData = $this->qrCodeService->generateQrCode($payload);

        // Persist the public PNG (mirrors participant qr storage shape).
        try {
            Storage::disk('public')->put("qrcodes/ticket_{$ticket->id}.png", base64_decode($qrData['qr_image']));
        } catch (\Exception $e) {
            Log::warning('Failed to persist ticket QR PNG', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }

        $ticket->qr_token = $qrData['token'];
        $ticket->qr_image = $qrData['qr_image'];
        $ticket->save();

        // Set the participant relation in memory for callers (markPaid email).
        if ($participant) {
            $ticket->setRelation('participant', $participant);
        }

        return $ticket;
    }

    /**
     * Mirror a ticket's QR onto its participant so legacy badge/email/scan-by-participant
     * paths stay unchanged. Also keeps a v1-style participant qr_payload for those paths.
     */
    private function mirrorQrOntoParticipant(Participant $participant, Ticket $ticket): void
    {
        // Preserve the participant's own v1 QR shape used by the legacy scan branch and
        // the scan-email re-generation (which reads participant->qr_payload).
        $participantPayload = $participant->qr_payload ?: [
            'id' => $participant->id,
            'uuid' => Str::uuid()->toString(),
            'email' => $participant->email,
            'access' => $participant->access_type,
        ];

        // Only (re)write the participant's mirrored image/token if it has none yet, so an
        // already-registered participant's existing v1 QR (in inboxes) is never clobbered.
        $update = ['qr_payload' => $participantPayload];

        if (empty($participant->qr_token)) {
            $update['qr_token'] = $ticket->qr_token;
            $update['qr_image'] = $ticket->qr_image;
        }

        $participant->update($update);
    }

    /**
     * Resolve (reuse or create) the participant for a paid-order attendee. Refreshes an
     * existing participant to 'accepted' for this event without overwriting other seats.
     */
    private function resolveParticipant(Order $order, ?Event $event, array $attendee, string $accessTier): Participant
    {
        $data = [
            'organizer_id' => $order->organizer_id,
            'event_id' => $event?->id,
            'first_name' => $attendee['first_name'],
            'last_name' => $attendee['last_name'],
            'company_name' => $attendee['company_name'] ?? null,
            'gender' => $order->gender,
            'phone' => $attendee['phone'] ?? null,
            'access_type' => $accessTier,
            'status' => 'accepted',
        ];

        $participant = Participant::where('email', $attendee['email'])->first();

        if ($participant) {
            $participant->update($data);

            return $participant;
        }

        return Participant::create($data + ['email' => $attendee['email']]);
    }

    /**
     * Legacy bridge: issue a ticket with event_id NULL for a no-event registration,
     * reusing the participant's already-written v1 QR (no tier, no capacity gate).
     */
    private function issueLegacyParticipantTicket(Participant $participant, string $accessType): Ticket
    {
        $ticket = new Ticket([
            'organizer_id' => $participant->organizer_id,
            'event_id' => null,
            'order_id' => null,
            'ticket_type_id' => null,
            'participant_id' => $participant->id,
            'attendee_first_name' => $participant->first_name,
            'attendee_last_name' => $participant->last_name,
            'attendee_email' => $participant->email,
            'attendee_company_name' => $participant->company_name,
            'attendee_phone' => $participant->phone,
            'access_tier' => $accessType,
            'ticket_code' => $this->generateTicketCode(),
            // Reuse the participant's existing v1 QR so the legacy scan branch keeps working.
            'qr_token' => $participant->qr_token,
            'qr_image' => $participant->qr_image,
            'status' => Ticket::STATUS_VALID,
            'download_token' => $this->generateTicketDownloadToken(),
            'expires_at' => null,
        ]);
        $ticket->save();
        $ticket->setRelation('participant', $participant);

        return $ticket;
    }

    /**
     * Resolve the event's DEFAULT free tier for /register: the is_default tier, else the
     * tier whose access_tier matches, else any active tier (preferring price 0). Returns
     * null only when the event has no ticket types at all (graceful: ticket still issues).
     */
    private function resolveDefaultFreeTicketType(Event $event, string $accessType): ?TicketType
    {
        $types = $event->ticketTypes()->where('is_active', true)->get();

        if ($types->isEmpty()) {
            return null;
        }

        // 1. The explicit default tier.
        $default = $types->firstWhere('is_default', true);
        if ($default) {
            return $default;
        }

        // 2. A tier matching the requested access_tier (prefer a free one).
        $matching = $types->where('access_tier', $accessType)->sortBy('price')->first();
        if ($matching) {
            return $matching;
        }

        // 3. Any active tier, cheapest first.
        return $types->sortBy('price')->first();
    }

    /**
     * INVENTORY gate (H8): assert the locked tier can fit $seats more, else throw.
     */
    private function assertInventory(TicketType $ticketType, int $seats): void
    {
        if (is_null($ticketType->quantity)) {
            return; // unlimited
        }

        if ((int) $ticketType->quantity_sold + $seats > (int) $ticketType->quantity) {
            throw new InventoryExceededException();
        }
    }

    /**
     * CAPACITY gate (H8): lock + count issued seats for the event and assert headroom.
     * Capacity is summed across ALL tiers, matching event.capacity semantics.
     */
    private function assertCapacity(Event $event, int $seats): void
    {
        if (is_null($event->capacity)) {
            return; // unlimited
        }

        $issuedSeats = Ticket::where('event_id', $event->id)
            ->whereIn('status', [Ticket::STATUS_VALID, Ticket::STATUS_CHECKED_IN])
            ->lockForUpdate()
            ->count();

        if ($issuedSeats + $seats > (int) $event->capacity) {
            throw new CapacityExceededException();
        }
    }

    /**
     * Normalize the buyer payload into validated order lines (one per tier).
     *
     * @param array<string, mixed> $buyer
     * @return array<int, array{ticket_type_id:?int, quantity:int, unit_price:float, line_total:float, access_tier:string}>
     */
    private function resolveOrderLines(Event $event, array $buyer): array
    {
        $rawItems = $buyer['items'] ?? null;

        // Legacy single-buyer shape -> ONE line against the matching/default tier.
        if (empty($rawItems)) {
            $accessType = trim((string) ($buyer['access_type'] ?? 'fair'));
            $quantity = max(1, (int) ($buyer['quantity'] ?? 1));
            $tier = $this->resolveDefaultFreeTicketType($event, $accessType);

            $unitPrice = $tier ? (float) $tier->price : (float) $event->ticket_price;

            $this->validateTier($event, $tier, $quantity);

            return [[
                'ticket_type_id' => $tier?->id,
                'quantity' => $quantity,
                'unit_price' => round($unitPrice, 2),
                'line_total' => round($unitPrice * $quantity, 2),
                'access_tier' => $tier?->access_tier ?? $accessType,
            ]];
        }

        $lines = [];
        foreach ($rawItems as $raw) {
            $quantity = max(1, (int) ($raw['quantity'] ?? 1));
            $tier = TicketType::where('id', $raw['ticket_type_id'] ?? 0)
                ->where('event_id', $event->id)
                ->first();

            $this->validateTier($event, $tier, $quantity);

            $unitPrice = (float) $tier->price;

            $lines[] = [
                'ticket_type_id' => $tier->id,
                'quantity' => $quantity,
                'unit_price' => round($unitPrice, 2),
                'line_total' => round($unitPrice * $quantity, 2),
                'access_tier' => $tier->access_tier,
            ];
        }

        return $lines;
    }

    /**
     * Validate a tier belongs to the event+organizer, is on sale, and respects max_per_order.
     */
    private function validateTier(Event $event, ?TicketType $tier, int $quantity): void
    {
        if (! $tier) {
            // No tier at all (event with no ticket types): allow legacy single-line fallback.
            return;
        }

        if ((int) $tier->event_id !== (int) $event->id) {
            throw new \InvalidArgumentException('Ticket type does not belong to this event.');
        }

        if (! $tier->isOnSale()) {
            throw new \InvalidArgumentException('Ticket type is not on sale.');
        }

        if (! is_null($tier->max_per_order) && $quantity > (int) $tier->max_per_order) {
            throw new \InvalidArgumentException('Quantity exceeds the per-order limit for this ticket type.');
        }
    }

    /**
     * Generate a unique human-facing order number.
     */
    private function generateOrderNumber(): string
    {
        do {
            $candidate = 'ORD-' . strtoupper(Str::random(10));
        } while (Order::where('order_number', $candidate)->exists());

        return $candidate;
    }

    /**
     * Generate a unique per-seat ticket code (the jti).
     */
    private function generateTicketCode(): string
    {
        do {
            $candidate = 'TCK-' . Str::upper(Str::random(20));
        } while (Ticket::where('ticket_code', $candidate)->exists());

        return $candidate;
    }

    /**
     * Generate a unique per-seat download token.
     */
    private function generateTicketDownloadToken(): string
    {
        do {
            $candidate = Str::random(64);
        } while (Ticket::where('download_token', $candidate)->exists());

        return $candidate;
    }

    /**
     * Generate a unique order-level download token (fallback when no ticket token exists).
     */
    private function generateOrderDownloadToken(): string
    {
        do {
            $candidate = Str::random(64);
        } while (Order::where('ticket_download_token', $candidate)->exists());

        return $candidate;
    }
}
