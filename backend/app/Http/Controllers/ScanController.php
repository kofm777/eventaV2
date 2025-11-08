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
    public function scan(Request $request): JsonResponse
    {
        $request->validate([
            'payload' => ['required', 'string'],
            'scanner_user' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            // Verify QR code signature
            $verification = $this->qrCodeService->verifyQrCode($request->payload);

            if (!$verification['valid']) {
                Log::warning('Invalid QR code scanned', [
                    'payload' => substr($request->payload, 0, 50) . '...',
                    'scanner_user' => $request->scanner_user,
                    'error' => $verification['error'] ?? 'Unknown error',
                ]);

                return response()->json([
                    'ok' => false,
                    'message' => 'QR code invalide ou corrompu.',
                ], 403);
            }

            $payload = $verification['payload'];

            // Find participant
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
                    'message' => 'Participant non trouvé.',
                ], 404);
            }

            // Check if participant is accepted
            if (!$participant->isAccepted()) {
                Log::warning('Non-accepted participant tried to scan', [
                    'participant_id' => $participant->id,
                    'status' => $participant->status,
                ]);

                return response()->json([
                    'ok' => false,
                    'message' => 'Accès refusé. Votre inscription n\'est pas encore validée.',
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
                    'id', 'first_name', 'last_name', 'email', 'access_type', 'status'
                ]),
                'scan_id' => $scan->id,
                'message' => "Bienvenue {$participant->full_name}!",
            ]);

        } catch (\Exception $e) {
            Log::error('Scan processing failed', [
                'error' => $e->getMessage(),
                'payload' => substr($request->payload, 0, 50) . '...',
                'scanner_user' => $request->scanner_user,
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Erreur lors du traitement du scan.',
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
