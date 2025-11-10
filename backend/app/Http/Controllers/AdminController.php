<?php

namespace App\Http\Controllers;

use App\Mail\ParticipantAccessMail;
use App\Models\Participant;
use App\Services\PdfBadgeService;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AdminController extends Controller
{
    public function __construct(
        private QrCodeService $qrCodeService,
        private PdfBadgeService $pdfBadgeService
    ) {}

    /**
     * Get all participants with optional filters
     */
    public function getParticipants(Request $request): JsonResponse
    {
        $query = Participant::query();

        // Filter by status
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        // Filter by access type
        if ($request->has('access_type') && $request->access_type !== '') {
            $query->where('access_type', $request->access_type);
        }

        // Search by name or email
        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $participants = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'ok' => true,
            'participants' => $participants,
        ]);
    }

    /**
     * Accept a participant
     */
    public function acceptParticipant(int $id): JsonResponse
    {
        try {
            $participant = Participant::findOrFail($id);

            if ($participant->status === 'accepted') {
                return response()->json([
                    'ok' => false,
                    'message' => 'Le participant est déjà accepté.',
                ], 400);
            }

            $participant->update(['status' => 'accepted']);

            // Regenerate QR code with updated status
            $qrData = $this->qrCodeService->generateQrCode($participant->qr_payload);

            // Update participant with new QR image
            $participant->update(['qr_image' => $qrData['qr_image']]);

            // Send confirmation email
            Mail::to($participant->email)->send(
                new ParticipantAccessMail($participant, $qrData['qr_image'])
            );

            Log::info('Participant accepted', [
                'participant_id' => $participant->id,
                'email' => $participant->email,
            ]);

            return response()->json([
                'ok' => true,
                'participant' => $participant,
                'message' => 'Participant accepté avec succès.',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to accept participant', [
                'participant_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Erreur lors de l\'acceptation du participant.',
            ], 500);
        }
    }

    /**
     * Reject a participant
     */
    public function rejectParticipant(int $id): JsonResponse
    {
        try {
            $participant = Participant::findOrFail($id);

            if ($participant->status === 'rejected') {
                return response()->json([
                    'ok' => false,
                    'message' => 'Le participant est déjà rejeté.',
                ], 400);
            }

            $participant->update(['status' => 'rejected']);

            // Send rejection email
            Mail::to($participant->email)->send(
                new ParticipantAccessMail($participant)
            );

            Log::info('Participant rejected', [
                'participant_id' => $participant->id,
                'email' => $participant->email,
            ]);

            return response()->json([
                'ok' => true,
                'participant' => $participant,
                'message' => 'Participant rejeté avec succès.',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to reject participant', [
                'participant_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Erreur lors du rejet du participant.',
            ], 500);
        }
    }

    /**
     * Delete a participant
     */
    public function deleteParticipant(int $id): JsonResponse
    {
        try {
            $participant = Participant::findOrFail($id);

            // Send deletion notification email
            try {
                Mail::to($participant->email)->send(
                    new ParticipantAccessMail($participant, null, 'deleted')
                );
            } catch (\Exception $e) {
                Log::warning('Failed to send deletion email to participant', [
                    'participant_id' => $participant->id,
                    'email' => $participant->email,
                    'error' => $e->getMessage(),
                ]);
            }

            $participant->delete();

            Log::info('Participant deleted', [
                'participant_id' => $id,
                'email' => $participant->email,
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Participant supprimé avec succès.',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete participant', [
                'participant_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Erreur lors de la suppression du participant.',
            ], 500);
        }
    }

    /**
     * Download PDF badge for participant
     */
    public function downloadBadge(int $id)
    {
        try {
            $participant = Participant::findOrFail($id);

            return $this->pdfBadgeService->downloadBadge($participant);

        } catch (\Exception $e) {
            Log::error('Failed to generate badge', [
                'participant_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Erreur lors de la génération du badge.',
            ], 500);
        }
    }
}
