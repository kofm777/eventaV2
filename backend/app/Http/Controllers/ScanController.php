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
            $qrToken = null;
            $participant = null;

            if ($request->payload) {
                $qrToken = $request->payload;
            } elseif ($request->qr_image) {
                $qrImageBase64 = preg_replace('#^data:image/[^;]+;base64,#', '', $request->qr_image);
                $participant = Participant::where('qr_image', $qrImageBase64)->first();
                if (!$participant) {
                    return response()->json(['ok' => false, 'message' => 'QR image not recognized.'], 404);
                }
                $qrToken = $participant->qr_token;
                if (!$qrToken) {
                    return response()->json(['ok' => false, 'message' => 'Corrupted QR data.'], 500);
                }
            }

            $verification = $this->qrCodeService->verifyQrCode($qrToken);
            if (!$verification['valid']) {
                Log::warning('Invalid QR code scanned', [
                    'scanner_user' => $request->scanner_user,
                    'error' => $verification['error'] ?? 'Unknown'
                ]);
                return response()->json(['ok' => false, 'message' => 'Invalid or corrupted QR code.'], 403);
            }

            $payload = $verification['payload'];
            if (!$participant) {
                $participant = Participant::where('id', $payload['id'])
                    ->where('email', $payload['email'])
                    ->first();
            }

            if (!$participant) {
                return response()->json(['ok' => false, 'message' => 'Participant not found.'], 404);
            }

            if ($participant->status !== 'accepted') {
                return response()->json([
                    'ok' => false,
                    'message' => 'Access denied. Registration not approved.',
                    'participant' => $participant->only([
                        'id', 'first_name', 'last_name', 'company_name', 'gender', 'email', 'access_type', 'status'
                    ])
                ], 403);
            }

            // 🔒 Conference access check
            if ($scanType === 'conference' && $participant->access_type !== 'fair + conference') {
                return response()->json([
                    'ok' => true,
                    'message' => 'Conference access not permitted for this participant.',
                    'participant' => $participant->only([
                        'id', 'first_name', 'last_name', 'company_name', 'gender', 'email', 'access_type', 'status'
                    ])
                ], 200);
            }

            // ✅ GLOBAL FULLY SCANNED CHECK (NEW)
            $isFullyScanned = (
                $participant->access_type === 'fair + conference' &&
                $participant->scanned_fair &&
                $participant->scanned_conference
            );

            $alreadyScanned = false;
            $message = '';

            if ($isFullyScanned) {
                $alreadyScanned = true;
                $message = "Your access has already been fully granted, {$participant->first_name} {$participant->last_name}.";
            } elseif ($scanType === 'fair' && $participant->scanned_fair) {
                $alreadyScanned = true;
                $message = "Fair access already granted for {$participant->first_name} {$participant->last_name}.";
            } elseif ($scanType === 'conference' && $participant->scanned_conference) {
                $alreadyScanned = true;
                $message = "Conference access already granted for {$participant->first_name} {$participant->last_name}.";
            }

            if ($alreadyScanned) {
                return response()->json([
                    'ok' => true,
                    'participant' => $participant->only([
                        'id', 'first_name', 'last_name', 'company_name', 'gender', 'email', 'access_type', 'status',
                        'scanned_fair', 'scanned_conference'
                    ]),
                    'is_already_scanned' => true,
                    'scan_type' => $scanType,
                    'message' => $message,
                ]);
            }

            // ✅ Perform atomic update
            \DB::transaction(function () use ($participant, $scanType) {
                if ($scanType === 'fair') {
                    $participant->scanned_fair = true;
                } else {
                    $participant->scanned_conference = true;
                }
                $participant->save();
            });

            $scan = Scan::create([
                'participant_id' => $participant->id,
                'scanned_at' => now(),
                'scanner_user' => $request->scanner_user,
                'raw_payload' => $payload,
            ]);

            Log::channel('scans')->info("Successful {$scanType} scan", [
                'scan_id' => $scan->id,
                'participant_id' => $participant->id,
                'access_type' => $participant->access_type,
                'scanner_user' => $request->scanner_user,
            ]);

            $welcome = $scanType === 'fair'
                ? "Welcome to the fair, {$participant->first_name}!"
                : "Welcome to the conference, {$participant->first_name}!";

            return response()->json([
                'ok' => true,
                'participant' => $participant->only([
                    'id', 'first_name', 'last_name', 'company_name', 'gender', 'email', 'access_type', 'status',
                    'scanned_fair', 'scanned_conference'
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