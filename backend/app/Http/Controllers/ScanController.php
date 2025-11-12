<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Models\Scan;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ScanController extends Controller
{
    public function __construct(
        private QrCodeService $qrCodeService
    ) {}

    /**
     * Scan QR code and log entry
     */
   /**
 * Scan QR code and log entry
 */
public function scan(Request $request): JsonResponse
{
    $request->validate([
        'payload' => ['nullable', 'string'],
        'qr_image' => ['nullable', 'string'],
        'scanner_user' => ['nullable', 'string', 'max:255'],
    ]);

    // Require one of the two
    if (!$request->payload && !$request->qr_image) {
        return response()->json([
            'ok' => false,
            'message' => 'Error during scan processing.',
        ], 500);
    }

    try {
        $qrToken = null;

        // Scenario 1: Direct QR token (from camera)
        if ($request->payload) {
            $qrToken = $request->payload;
        }
        // Scenario 2: Uploaded QR image (base64)
        elseif ($request->qr_image) {
            // Remove data URL prefix if present (e.g., "data:image/png;base64,")
            $qrImageBase64 = preg_replace('#^data:image/[^;]+;base64,#', '', $request->qr_image);

            // Find participant by qr_image
            $participant = Participant::where('qr_image', $qrImageBase64)->first();

            if (!$participant) {
                Log::warning('No participant found for uploaded QR image');
                return response()->json([
                    'ok' => false,
                    'message' => 'QR image not recognized.',
                ], 404);
            }

            // Use the participant's stored qr_token for verification
            $qrToken = $participant->qr_token;

            if (!$qrToken) {
                Log::error('Participant has no qr_token', ['participant_id' => $participant->id]);
                return response()->json([
                    'ok' => false,
                    'message' => 'Corrupted QR data.',
                ], 500);
            }
        }

        // Now verify the qrToken (same for both scenarios)
        $verification = $this->qrCodeService->verifyQrCode($qrToken);

        if (!$verification['valid']) {
            Log::warning('Invalid QR code scanned', [
                'qr_image_provided' => (bool) $request->qr_image,
                'scanner_user' => $request->scanner_user,
                'error' => $verification['error'] ?? 'Unknown error',
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Invalid or corrupted QR code.',
            ], 403);
        }

        $payload = $verification['payload'];

        // Find participant by ID + email (from payload)
        $participant = Participant::where('id', $payload['id'])
            ->where('email', $payload['email'])
            ->first();

        if (!$participant) {
            Log::warning('Participant not found for QR code', [
                'payload_id' => $payload['id'] ?? 'unknown',
                'payload_email' => $payload['email'] ?? 'unknown',
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Participant not found.',
            ], 404);
        }

        if (!$participant->isAccepted()) {
            Log::warning('Non-accepted participant tried to scan', [
                'participant_id' => $participant->id,
                'status' => $participant->status,
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Access denied. Your registration is not yet validated.',
                'participant' => $participant->only(['id', 'first_name', 'last_name', 'status']),
            ], 403);
        }

        // Create scan record
        $scan = Scan::create([
            'participant_id' => $participant->id,
            'scanned_at' => now(),
            'scanner_user' => $request->scanner_user,
            'raw_payload' => $payload,
        ]);

        Log::channel('scans')->info('Successful scan', [
            'scan_id' => $scan->id,
            'participant_id' => $participant->id,
            'participant_name' => $participant->full_name,
            'access_type' => $participant->access_type,
            'scanner_user' => $request->scanner_user,
        ]);

        return response()->json([
            'ok' => true,
            'participant' => $participant->only([
                'id', 'first_name', 'last_name', 'company_name', 'gender', 'email', 'access_type', 'status'
            ]),
            'scan_id' => $scan->id,
            'message' => "Welcome {$participant->full_name}!",
        ]);

    } catch (\Exception $e) {
        Log::error('Scan processing failed', [
            'error' => $e->getMessage(),
            'scanner_user' => $request->scanner_user,
        ]);

        return response()->json([
            'ok' => false,
            'message' => 'Error during scan processing.',
        ], 500);
    }
}

    /**
     * Get recent scans (for admin)
     */
    public function getRecentScans(Request $request): JsonResponse
    {
        $scans = Scan::with('participant')
            ->orderBy('scanned_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'ok' => true,
            'scans' => $scans,
        ]);
    }
}
