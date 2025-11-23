<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterParticipantRequest;
use App\Mail\ParticipantAccessMail;
use App\Models\Participant;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class RegistrationController extends Controller
{
    public function __construct(
        private QrCodeService $qrCodeService
    ) {}

    /**
     * Register a new participant
     */
    public function register(RegisterParticipantRequest $request): JsonResponse
    {
        try {
            $accessType = trim($request->access_type);

            // Create participant
            $participant = Participant::create([
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

        // Send email with PUBLIC URL
        try {
            Mail::to($participant->email)
                ->send(new ParticipantAccessMail($participant, $qrUrl));
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
}