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
            // Create participant
            $participant = Participant::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'gender' => $request->gender,
                'phone' => $request->phone,
                'email' => $request->email,
                'access_type' => $request->access_type,
                'status' => 'accepted', // Always accepted
            ]);

            // Generate QR code payload
            $qrPayload = [
                'id' => $participant->id,
                'uuid' => Str::uuid()->toString(),
                'email' => $participant->email,
                'access' => $participant->access_type,
            ];

            // Generate QR code with HMAC signature
            $qrData = $this->qrCodeService->generateQrCode($qrPayload);

            // Update participant with QR data
            $participant->update([
                'qr_token' => $qrData['token'],
                'qr_payload' => $qrPayload,
                'qr_image' => $qrData['qr_image'],
            ]);

            // Send email with QR code
            try {
                Mail::to($participant->email)->send(
                    new ParticipantAccessMail($participant, $qrData['qr_image'])
                );
                $emailSent = true;
            } catch (\Exception $e) {
                Log::warning('Failed to send email to participant', [
                    'participant_id' => $participant->id,
                    'email' => $participant->email,
                    'error' => $e->getMessage(),
                ]);
                $emailSent = false;
            }

            Log::info('Participant registered successfully', [
                'participant_id' => $participant->id,
                'email' => $participant->email,
            ]);

            return response()->json([
                'ok' => true,
                'participant' => $participant->only([
                    'id', 'first_name', 'last_name', 'email', 'access_type', 'status'
                ]),
                'qr' => $qrData['qr_image'],
                'email_sent' => $emailSent,
                'message' => 'Inscription réussie. ' . ($emailSent ? 'Un email avec votre QR code vous a été envoyé.' : 'Votre QR code est prêt à être téléchargé.'),
            ]);

        } catch (\Exception $e) {
            Log::error('Registration failed', [
                'error' => $e->getMessage(),
                'email' => $request->email ?? 'unknown',
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Une erreur est survenue lors de l\'inscription.',
            ], 500);
        }
    }
}
