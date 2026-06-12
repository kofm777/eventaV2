<?php

namespace App\Services;

use App\Mail\ParticipantAccessMail;
use App\Models\Event;
use App\Models\Order;
use App\Models\Participant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Owns the order business lifecycle so a payment-gateway swap never touches it.
 */
class OrderService
{
    public function __construct(
        private QrCodeService $qrCodeService
    ) {
    }

    /**
     * Create a PENDING_PAYMENT order for the given event from validated buyer data.
     *
     * @param array<string, mixed> $buyer first_name,last_name,company_name,gender,phone,email,access_type,quantity
     */
    public function createOrder(Event $event, array $buyer): Order
    {
        $quantity = max(1, (int) ($buyer['quantity'] ?? 1));
        $amountTotal = round((float) $event->ticket_price * $quantity, 2);

        return Order::create([
            // Tenant the order to the event's organizer. The guest purchase flow is
            // unauthenticated (no HTTP organizer context), so we derive it from the
            // event explicitly instead of relying on the auto-stamp hook.
            'organizer_id' => $event->organizer_id,
            'order_number' => $this->generateOrderNumber(),
            'event_id' => $event->id,
            'buyer_email' => $buyer['email'],
            'buyer_first_name' => $buyer['first_name'],
            'buyer_last_name' => $buyer['last_name'],
            'buyer_company_name' => $buyer['company_name'] ?? null,
            'buyer_phone' => $buyer['phone'] ?? null,
            // Store gender/access_type verbatim as received, matching existing /register behavior.
            'gender' => $buyer['gender'],
            'access_type' => trim($buyer['access_type']),
            'quantity' => $quantity,
            'amount_total' => $amountTotal,
            'currency' => $event->currency,
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_provider' => 'stub',
        ]);
    }

    /**
     * Transition the order to PAID and issue the ticket (participant + QR + email + download token).
     *
     * Idempotent: safe to call twice (confirm endpoint + webhook could both fire). If the order
     * is already PAID or already has a participant, the existing order is returned untouched.
     */
    public function markPaidAndIssueTicket(Order $order, string $reference = ''): Order
    {
        // Idempotency guard (fast path, before the transaction).
        if ($order->status === Order::STATUS_PAID || $order->participant_id) {
            return $order;
        }

        return DB::transaction(function () use ($order, $reference) {
            // Re-read with a lock to avoid a double-issue race (confirm + webhook).
            $order = Order::whereKey($order->getKey())->lockForUpdate()->first();

            if ($order->status === Order::STATUS_PAID || $order->participant_id) {
                return $order;
            }

            // a. Create the participant from buyer_* fields (status 'accepted' mirrors /register).
            //    participants.email is UNIQUE, but the guest flow lets buyers re-buy with an
            //    email that may already exist (prior /register or a previous purchase). Reuse the
            //    existing participant in that case (refreshing it to 'accepted' for this event)
            //    instead of throwing a duplicate-key QueryException that would 500 the request.
            $participantData = [
                // Inherit the order's tenant so issued tickets are correctly tenanted.
                'organizer_id' => $order->organizer_id,
                'event_id' => $order->event_id,
                'first_name' => $order->buyer_first_name,
                'last_name' => $order->buyer_last_name,
                'company_name' => $order->buyer_company_name,
                'gender' => $order->gender,
                'phone' => $order->buyer_phone,
                'access_type' => $order->access_type,
                'status' => 'accepted',
            ];

            $participant = Participant::where('email', $order->buyer_email)->first();

            if ($participant) {
                $participant->update($participantData);
            } else {
                $participant = Participant::create($participantData + ['email' => $order->buyer_email]);
            }

            // b. Build the SAME QR payload shape used today, plus a harmless event_id key.
            $qrPayload = [
                'id' => $participant->id,
                'uuid' => Str::uuid()->toString(),
                'email' => $participant->email,
                'access' => $participant->access_type,
                'event_id' => $order->event_id,
            ];

            // c. Generate QR as base64 and write the public PNG exactly like RegistrationController.
            $qrData = $this->qrCodeService->generateQrCode($qrPayload);

            $qrFileName = "qrcodes/participant_{$participant->id}.png";
            Storage::disk('public')->put($qrFileName, base64_decode($qrData['qr_image']));

            $participant->update([
                'qr_token' => $qrData['token'],
                'qr_payload' => $qrPayload,
                'qr_image' => $qrData['qr_image'],
            ]);

            // d. Mark order PAID and issue a secure, unique download token.
            $order->participant_id = $participant->id;
            $order->status = Order::STATUS_PAID;
            $order->paid_at = now();
            $order->payment_reference = $reference !== '' ? $reference : ($order->payment_reference ?: 'stub_ref_' . Str::random(16));
            $order->ticket_download_token = $this->generateDownloadToken();
            $order->save();

            // e. Email the ticket (REUSE existing mailable) with the no-login download link.
            $ticketUrl = rtrim(config('app.frontend_url'), '/') . '/ticket/' . $order->ticket_download_token;

            try {
                Mail::to($order->buyer_email)->send(
                    new ParticipantAccessMail($participant, $qrData['qr_image'], 'default', $ticketUrl)
                );
            } catch (\Exception $e) {
                Log::warning('Failed to send ticket email', [
                    'order_number' => $order->order_number,
                    'email' => $order->buyer_email,
                    'error' => $e->getMessage(),
                ]);
            }

            return $order;
        });
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
     * Generate a unique high-entropy ticket download token.
     */
    private function generateDownloadToken(): string
    {
        do {
            $candidate = Str::random(64);
        } while (Order::where('ticket_download_token', $candidate)->exists());

        return $candidate;
    }
}
