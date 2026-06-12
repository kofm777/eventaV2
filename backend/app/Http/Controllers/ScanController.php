<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Models\Scan;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
       ]);

       if (!$request->payload && !$request->qr_image) {
           return response()->json(['ok' => false, 'message' => 'Missing scan data.'], 400);
       }

       try {
           $participant = null;
           $qrToken = $request->payload ?? null;

           if ($request->qr_image) {
               // Clean base64 if sent
               $qrImageBase64 = preg_replace('#^data:image/[^;]+;base64,#', '', $request->qr_image);

               // Find participant by matching base64 QR image (if stored as base64)
               // But since we store URL, we need to match by token or ID
               // So better to verify via token
               $verification = $this->qrCodeService->verifyQrCode($qrImageBase64); // ← This won't work if it's not token

               if (!$verification['valid']) {
                   return response()->json(['ok' => false, 'message' => 'Invalid QR code.'], 403);
               }

               $payload = $verification['payload'];
               $participant = Participant::where('id', $payload['id'])
                   ->where('email', $payload['email'])
                   ->first();

               if (!$participant) {
                   return response()->json(['ok' => false, 'message' => 'Participant not found.'], 404);
               }

               $qrToken = $participant->qr_token;
           }

           if (!$participant && $qrToken) {
               $verification = $this->qrCodeService->verifyQrCode($qrToken);
               if (!$verification['valid']) {
                   Log::warning('Invalid QR code scanned', [
                       'scanner_user' => $request->scanner_user,
                       'error' => $verification['error'] ?? 'Unknown'
                   ]);
                   return response()->json(['ok' => false, 'message' => 'Invalid or corrupted QR code.'], 403);
               }
               $payload = $verification['payload'];
               $participant = Participant::where('id', $payload['id'])
                   ->where('email', $payload['email'])
                   ->first();
               if (!$participant) {
                   return response()->json(['ok' => false, 'message' => 'Participant not found.'], 404);
               }
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
           $alreadyScanned = false;
           $message = '';
           if (($scanType === 'fair' && $participant->scanned_fair) ||
               ($scanType === 'conference' && $participant->scanned_conference) ||
               ($participant->scanned_fair && $participant->scanned_conference)) {
               $alreadyScanned = true;
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
           \DB::transaction(function () use ($participant, $scanType) {
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
    // Generate QR code again as base64 for email
    $qrData = $this->qrCodeService->generateQrCode($participant->qr_payload);

    Mail::to($participant->email)->queue(
        new ParticipantAccessMail(
            participant: $participant,
            qrImageBase64: $qrData['qr_image'], // ← PASS BASE64 HERE
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

       } catch (\Exception $e) {
           Log::error('Scan processing failed', [
               'error' => $e->getMessage(),
               'scanner_user' => $request->scanner_user,
           ]);
           return response()->json(['ok' => false, 'message' => 'Scan processing error.'], 500);
       }
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
