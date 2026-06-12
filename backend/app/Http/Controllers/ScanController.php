<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Participant;
use App\Models\Scan;
use App\Models\Ticket;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\ParticipantAccessMail;

class ScanController extends Controller
{
    public function __construct(private QrCodeService $qrCodeService) {}

   private function processScan(Request $request, string $scanType): JsonResponse
   {
       $request->validate([
           'payload' => ['nullable', 'string'],
           'qr_image' => ['nullable', 'string'],
           'scanner_user' => ['nullable', 'string', 'max:255'],
           'event_id' => ['nullable', 'integer'],
       ]);

       if (!$request->payload && !$request->qr_image) {
           return response()->json(['ok' => false, 'message' => 'Missing scan data.'], 400);
       }

       try {
           $qrToken = $request->payload ?: ($request->qr_image
               ? preg_replace('#^data:image/[^;]+;base64,#', '', $request->qr_image)
               : null);

           if (!$qrToken) {
               return response()->json(['ok' => false, 'message' => 'Missing scan data.'], 400);
           }

           // 1. Verify signature (authenticity). Failure -> 403 (unchanged).
           $verification = $this->qrCodeService->verifyQrCode($qrToken);

           if (!$verification['valid']) {
               Log::warning('Invalid QR code scanned', [
                   'scanner_user' => $request->scanner_user,
                   'error' => $verification['error'] ?? 'Unknown',
               ]);
               return response()->json(['ok' => false, 'message' => 'Invalid or corrupted QR code.'], 403);
           }

           $payload = $verification['payload'];

           // Detection is purely by payload shape: v2 + tid/jti => new per-ticket QR.
           if (is_array($payload) && ($payload['v'] ?? null) === 2 && isset($payload['tid'], $payload['jti'])) {
               return $this->scanTicket($request, $scanType, $payload, $qrToken);
           }

           // Otherwise fall through to the LEGACY participant QR path (verbatim).
           return $this->scanLegacyParticipant($request, $scanType, $payload, $qrToken);

       } catch (\Exception $e) {
           Log::error('Scan processing failed', [
               'error' => $e->getMessage(),
               'scanner_user' => $request->scanner_user,
           ]);
           return response()->json(['ok' => false, 'message' => 'Scan processing error.'], 500);
       }
   }

   /**
    * BRANCH A — NEW per-ticket v2 QR. Organizer-AND-event-AND-tier scoped, per-ticket
    * idempotent check-in under a row lock. The BelongsToOrganizer global scope already
    * filters Ticket queries to the scanning admin's organizer (the H4 fix).
    *
    * @param array<string, mixed> $payload
    */
   private function scanTicket(Request $request, string $scanType, array $payload, string $qrToken): JsonResponse
   {
       // Organizer-scoped load (global scope filters to the scanner's organizer).
       $ticket = Ticket::where('id', $payload['tid'])
           ->where('ticket_code', $payload['jti'])
           ->first();

       if (!$ticket) {
           return response()->json(['ok' => false, 'message' => 'Ticket not for this event/organizer.'], 404);
       }

       // Event-scoped: bind to the target event (request -> scanner's single active event
       // -> payload eid). When a target is determinable, mismatches are rejected.
       $targetEventId = $request->input('event_id') ?: $this->resolveScannerEventId($request, $payload);

       if ($targetEventId && $ticket->event_id && (int) $ticket->event_id !== (int) $targetEventId) {
           return response()->json(['ok' => false, 'message' => 'Ticket not for this event.'], 403);
       }

       // Tier/access check (mirrors today's conference gate).
       if ($scanType === 'conference' && $ticket->access_tier !== 'fair + conference') {
           return response()->json([
               'ok' => true,
               'message' => 'Conference access not permitted for this ticket.',
               'participant' => $this->ticketWelcomeData($ticket),
           ], 200);
       }

       // Expiry gate (payload exp pre-check + durable expires_at).
       if ($this->qrCodeService->isExpired($payload) || $ticket->isExpired()) {
           return response()->json(['ok' => false, 'message' => 'Ticket expired.'], 403);
       }

       // Status gate: revoked tickets fail even though the signature verifies.
       if ($ticket->isRevoked()) {
           return response()->json(['ok' => false, 'message' => 'Ticket revoked.'], 403);
       }

       // Idempotent double-scan guard PER TICKET (the H6 fix — no per-person booleans).
       if ($ticket->isCheckedIn()) {
           return response()->json([
               'ok' => true,
               'participant' => $this->ticketWelcomeData($ticket),
               'is_already_scanned' => true,
               'scan_type' => $scanType,
               'checked_in_at' => $ticket->checked_in_at,
               'message' => "Access already granted for {$ticket->attendee_first_name} {$ticket->attendee_last_name}.",
           ]);
       }

       // Check in under a row lock; record the scan with ticket_id for the audit trail.
       $scan = DB::transaction(function () use ($ticket, $request, $scanType, $qrToken) {
           $locked = Ticket::whereKey($ticket->id)->lockForUpdate()->first();

           // Re-check inside the lock (a concurrent scan may have just checked it in).
           if ($locked->status === Ticket::STATUS_CHECKED_IN) {
               return null;
           }

           $locked->status = Ticket::STATUS_CHECKED_IN;
           $locked->checked_in_at = now();
           $locked->scanner_user = $request->scanner_user;
           $locked->save();

           $ticket->refresh();

           return Scan::create([
               'organizer_id' => $ticket->organizer_id,
               'participant_id' => $ticket->participant_id,
               'ticket_id' => $ticket->id,
               'scanned_at' => now(),
               'scanner_user' => $request->scanner_user,
               'raw_payload' => $qrToken,
               'scan_type' => $scanType,
           ]);
       });

       // Lost the race: another scanner checked it in first -> idempotent already-scanned.
       if ($scan === null) {
           $ticket->refresh();
           return response()->json([
               'ok' => true,
               'participant' => $this->ticketWelcomeData($ticket),
               'is_already_scanned' => true,
               'scan_type' => $scanType,
               'checked_in_at' => $ticket->checked_in_at,
               'message' => "Access already granted for {$ticket->attendee_first_name} {$ticket->attendee_last_name}.",
           ]);
       }

       $welcome = $scanType === 'fair'
           ? "Welcome to the fair, {$ticket->attendee_first_name}!"
           : "Welcome to the conference, {$ticket->attendee_first_name}!";

       return response()->json([
           'ok' => true,
           'participant' => $this->ticketWelcomeData($ticket),
           'scan_id' => $scan->id,
           'is_already_scanned' => false,
           'scan_type' => $scanType,
           'message' => $welcome,
       ]);
   }

   /**
    * Welcome payload shaped like today (id/name/access/status) sourced from the ticket
    * (+ participant when present), so the scanner UI renders unchanged.
    *
    * @return array<string, mixed>
    */
   private function ticketWelcomeData(Ticket $ticket): array
   {
       return [
           'id' => $ticket->participant_id ?? $ticket->id,
           'ticket_id' => $ticket->id,
           'first_name' => $ticket->attendee_first_name,
           'last_name' => $ticket->attendee_last_name,
           'gender' => optional($ticket->participant)->gender,
           'access_type' => $ticket->access_tier,
           'status' => $ticket->status,
           // Mirror the per-person flags for UI compatibility (derived from this seat).
           'scanned_fair' => in_array($ticket->status, [Ticket::STATUS_CHECKED_IN], true),
           'scanned_conference' => $ticket->access_tier === 'fair + conference'
               && $ticket->status === Ticket::STATUS_CHECKED_IN,
       ];
   }

   /**
    * Best-effort target event resolution for a scan: the payload's eid, else the scanning
    * admin's single active (published) event when they own exactly one. NULL => no
    * event-binding assertion (back-compat with single-event organizers).
    *
    * @param array<string, mixed> $payload
    */
   private function resolveScannerEventId(Request $request, array $payload): ?int
   {
       if (!empty($payload['eid'])) {
           return (int) $payload['eid'];
       }

       // Organizer-scoped: the global scope already filters to the scanner's organizer.
       $events = Event::query()->limit(2)->pluck('id');

       return $events->count() === 1 ? (int) $events->first() : null;
   }

   /**
    * BRANCH B — LEGACY participant QR path (verbatim from the pre-Phase-2 controller).
    * Keeps every already-issued participant QR scanning unchanged during the transition.
    *
    * @param array<string, mixed> $payload
    */
   private function scanLegacyParticipant(Request $request, string $scanType, array $payload, string $qrToken): JsonResponse
   {
       $participant = Participant::where('id', $payload['id'] ?? null)
           ->where('email', $payload['email'] ?? null)
           ->first();

       if (!$participant) {
           return response()->json(['ok' => false, 'message' => 'Participant not found.'], 404);
       }

       if ($participant->status !== 'accepted') {
           return response()->json([
               'ok' => false,
               'message' => 'Access denied. Registration not approved.',
               'participant' => $participant->only([
                   'id','first_name','last_name','gender','access_type','status'
               ])
           ], 403);
       }

       // Conference access check
       if ($scanType === 'conference' && $participant->access_type !== 'fair + conference') {
           return response()->json([
               'ok' => true,
               'message' => 'Conference access not permitted for this participant.',
               'participant' => $participant->only([
                   'id','first_name','last_name','gender','access_type','status'
               ])
           ], 200);
       }

       // Check already scanned
       if (($scanType === 'fair' && $participant->scanned_fair) ||
           ($scanType === 'conference' && $participant->scanned_conference) ||
           ($participant->scanned_fair && $participant->scanned_conference)) {
           $message = "Access already granted for {$participant->first_name} {$participant->last_name}.";
           return response()->json([
               'ok' => true,
               'participant' => $participant->only([
                   'id','first_name','last_name','gender','access_type','status',
                   'scanned_fair','scanned_conference'
               ]),
               'is_already_scanned' => true,
               'scan_type' => $scanType,
               'message' => $message,
           ]);
       }

       // Update scan status atomically
       DB::transaction(function () use ($participant, $scanType) {
           if ($scanType === 'fair') {
               $participant->scanned_fair = true;
           } else {
               $participant->scanned_conference = true;
           }
           $participant->save();
       });

       // Create scan record
       $scan = Scan::create([
           'participant_id' => $participant->id,
           'scanned_at' => now(),
           'scanner_user' => $request->scanner_user,
           'raw_payload' => $qrToken,
           'scan_type' => $scanType,
       ]);

       // Send email after scan with BASE64 QR image
       try {
           $qrData = $this->qrCodeService->generateQrCode($participant->qr_payload);

           Mail::to($participant->email)->queue(
               new ParticipantAccessMail(
                   participant: $participant,
                   qrImageBase64: $qrData['qr_image'],
                   emailType: $scanType
               )
           );
       } catch (\Exception $e) {
           Log::warning('Failed to send scan email', [
               'participant_id' => $participant->id,
               'email' => $participant->email,
               'error' => $e->getMessage(),
           ]);
       }

       $welcome = $scanType === 'fair'
           ? "Welcome to the fair, {$participant->first_name}!"
           : "Welcome to the conference, {$participant->first_name}!";

       return response()->json([
           'ok' => true,
           'participant' => $participant->only([
               'id','first_name','last_name','gender','access_type','status',
               'scanned_fair','scanned_conference'
           ]),
           'scan_id' => $scan->id,
           'is_already_scanned' => false,
           'scan_type' => $scanType,
           'message' => $welcome,
       ]);
   }

    public function scanFair(Request $request): JsonResponse
    {
        return $this->processScan($request, 'fair');
    }

    public function scanConference(Request $request): JsonResponse
    {
        return $this->processScan($request, 'conference');
    }

    public function getRecentScans(Request $request): JsonResponse
    {
        $scans = Scan::with('participant')
            ->orderBy('scanned_at', 'desc')
            ->limit(50)
            ->get();
        return response()->json(['ok' => true, 'scans' => $scans]);
    }
}
