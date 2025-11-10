<?php

namespace App\Services;

use App\Models\Participant;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Log;

class PdfBadgeService
{
    /**
     * Generate PDF badge for participant
     */
    public function generateBadge(Participant $participant): string
    {
        try {
            // Prepare data for the badge
            $data = [
                'participant' => $participant,
                'qr_image' => $participant->qr_token ? $this->generateQrImage($participant) : null,
                'event_name' => config('app.name', 'Event Access'),
                'generated_at' => now()->format('d/m/Y H:i'),
            ];

            // Generate HTML from view
            $html = view('badge.participant', $data)->render();

            // Create Dompdf instance
            $dompdf = new Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('a6', 'landscape');
            $dompdf->render();

            // Return PDF as base64 string
            return base64_encode($dompdf->output());

        } catch (\Exception $e) {
            Log::error('PDF badge generation failed', [
                'participant_id' => $participant->id,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Erreur lors de la génération du badge PDF: ' . $e->getMessage());
        }
    }

    /**
     * Generate QR code image for badge
     */
    private function generateQrImage(Participant $participant): string
    {
        $qrService = app(QrCodeService::class);

        // Regenerate QR code if needed
        if (!$participant->qr_token) {
            $qrPayload = [
                'id' => $participant->id,
                'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                'email' => $participant->email,
                'access' => $participant->access_type,
            ];

            $qrData = $qrService->generateQrCode($qrPayload);
            $participant->update([
                'qr_token' => $qrData['token'],
                'qr_payload' => $qrPayload,
            ]);

            return $qrData['qr_image'];
        }

        // Use existing QR code
        $qrData = $qrService->generateQrCode($participant->qr_payload);
        return $qrData['qr_image'];
    }

    /**
     * Get PDF badge as downloadable response
     */
    public function downloadBadge(Participant $participant)
    {
        $pdfBase64 = $this->generateBadge($participant);

        return response()->stream(function () use ($pdfBase64) {
            echo base64_decode($pdfBase64);
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="badge-' . $participant->id . '.pdf"',
        ]);
    }
}
