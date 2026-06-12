<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterParticipantRequest;
use App\Mail\ParticipantAccessMail;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Participant;
use App\Services\OrderService;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class RegistrationController extends Controller
{
    public function __construct(
        private QrCodeService $qrCodeService,
        private OrderService $orderService
    ) {}

    /**
     * Register a new participant
     */
    public function register(RegisterParticipantRequest $request): JsonResponse
    {
        try {
            $accessType = trim($request->access_type);

            // /register is UNAUTHENTICATED -> there is no organizer context, so the
            // creating() auto-stamp hook won't fire. Resolve the tenant explicitly:
            // the target event's organizer when an event is supplied, else the Demo
            // Organizer (Phase 0 has no event selection -> always Demo Organizer),
            // keeping the participant visible to the super-admin and Demo Organizer.
            $organizerId = $this->resolveOrganizerId($request->input('event_id'));

            // Create participant
            $participant = Participant::create([
                'organizer_id' => $organizerId,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'company_name' => $request->company_name,
                'gender' => $request->gender,
                'phone' => $request->phone,
                'email' => $request->email,
                'access_type' => $accessType,
                'status' => 'accepted', // Always accepted
            ]);

            // Generate QR payload
            $qrPayload = [
                'id' => $participant->id,
                'uuid' => Str::uuid()->toString(),
                'email' => $participant->email,
                'access' => $participant->access_type,
            ];

        // Generate QR code as BASE64
        $qrData = $this->qrCodeService->generateQrCode($qrPayload);

        // Save QR code as PNG file in public disk (for badge PDF / scanning)
        $qrFileName = "qrcodes/participant_{$participant->id}.png";
        Storage::disk('public')->put($qrFileName, base64_decode($qrData['qr_image']));
        $qrUrl = Storage::disk('public')->url($qrFileName);

        // Update participant record
        $participant->update([
            'qr_token' => $qrData['token'],
            'qr_payload' => $qrPayload,
            'qr_image' => $qrData['qr_image'], // Store public URL for scanning/badge
        ]);

        // Phase 2: ALSO issue a free ticket row via the event's default free tier (under
        // the inventory/capacity lock). The participant + its v1 QR above are untouched,
        // so the response shape below stays byte-for-byte identical. A no-event (Demo)
        // registration issues a legacy bridge ticket (event_id NULL) and never 500s.
        $event = !empty($request->input('event_id')) ? Event::find($request->input('event_id')) : null;

        try {
            $this->orderService->issueFreeTicket($event, $participant, $accessType);
        } catch (\App\Exceptions\InventoryExceededException | \App\Exceptions\CapacityExceededException $e) {
            // Roll back the just-created participant so a sold-out/at-capacity event does
            // not leave an orphan accepted participant with no seat.
            $participant->delete();

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            // Never break the live free-registration path on a ticket-issuance hiccup:
            // the participant + QR + email still go out exactly as before.
            Log::warning('Free ticket issuance failed; participant kept', [
                'participant_id' => $participant->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Send email with PUBLIC URL
        try {
          Mail::to($participant->email)
              ->send(new ParticipantAccessMail($participant, $qrData['qr_image']));
            $emailSent = true;
        } catch (\Exception $e) {
            Log::warning('Failed to send email to participant', [
                'participant_id' => $participant->id,
                'email' => $participant->email,
                'error' => $e->getMessage(),
            ]);
            $emailSent = false;
        }

        // Return response with BOTH qr_url and qr_base64
        return response()->json([
            'ok' => true,
           'participant' => $participant->only([
                               'id', 'first_name', 'last_name','company_name', 'email', 'access_type', 'status'
                           ]),
                           'qr' => $qrData['qr_image'],
                           'qr_url' => $qrUrl,
            'email_sent' => $emailSent,
            'message' => 'Registration successful. QR code sent via email.',
        ]);

        } catch (\Exception $e) {
            Log::error('Registration failed', [
                'error' => $e->getMessage(),
                'email' => $request->email ?? 'unknown',
            ]);
            return response()->json([
                'ok' => false,
                'message' => 'An error occurred during registration.',
            ], 500);
        }
    }

    /**
     * Resolve the organizer a public (unauthenticated) registration belongs to:
     *  - the target event's organizer_id when a valid event_id is provided;
     *  - otherwise the Demo Organizer (the Phase 0 default, preserving today's
     *    single-tenant behavior). Returns null only if no Demo Organizer exists.
     */
    private function resolveOrganizerId(mixed $eventId): ?int
    {
        if (! empty($eventId)) {
            $event = Event::find($eventId);

            if ($event && $event->organizer_id) {
                return $event->organizer_id;
            }
        }

        return Organizer::where('slug', 'demo-organizer')->value('id');
    }
}