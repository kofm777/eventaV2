<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\PdfBadgeService;
use Illuminate\Http\JsonResponse;

/**
 * Public, no-login ticket access via a high-entropy ticket_download_token.
 *
 * The token is the only credential. It is unique-indexed and only ever yields
 * read access to that one PAID order's QR/PDF (no id enumeration, no admin scope).
 * Both routes are throttled to prevent brute-force.
 */
class TicketController extends Controller
{
    public function __construct(
        private PdfBadgeService $pdfBadgeService
    ) {
    }

    /**
     * Guest views their issued ticket (QR + details) with no login.
     */
    public function show(string $token): JsonResponse
    {
        $order = $this->resolvePaidOrder($token);

        if (!$order || !$order->participant) {
            return response()->json([
                'ok' => false,
                'message' => 'Ticket not found.',
            ], 404);
        }

        $participant = $order->participant;
        $event = $order->event;

        return response()->json([
            'ok' => true,
            'ticket' => [
                'event_name' => $event?->name,
                'buyer_name' => trim($order->buyer_first_name . ' ' . $order->buyer_last_name),
                'access_type' => $order->access_type,
                'status' => $order->status,
                // Reuse the stored base64 PNG (NO data: prefix; frontend adds it).
                'qr_image' => $participant->qr_image,
                'download_pdf_url' => rtrim(config('app.frontend_url'), '/') . '/ticket/' . $token,
            ],
        ]);
    }

    /**
     * Guest downloads their PDF badge by token (REUSES PdfBadgeService unchanged).
     */
    public function badge(string $token)
    {
        $order = $this->resolvePaidOrder($token);

        if (!$order || !$order->participant) {
            return response()->json([
                'ok' => false,
                'message' => 'Ticket not found.',
            ], 404);
        }

        return $this->pdfBadgeService->downloadBadge($order->participant);
    }

    /**
     * Resolve a PAID order by its download token, or null when invalid / not paid.
     */
    private function resolvePaidOrder(string $token): ?Order
    {
        return Order::where('ticket_download_token', $token)
            ->where('status', Order::STATUS_PAID)
            ->with(['participant', 'event'])
            ->first();
    }
}
