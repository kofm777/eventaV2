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
use App\Models\Scan;
use Illuminate\Support\Facades\DB;

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

    // Apply filters FIRST (on raw DB columns)
    if ($request->has('status') && $request->status !== '') {
        $status = $request->status;

        if ($status === 'fair_scanned') {
            $query->where('scanned_fair', true)
                  ->where('scanned_conference', false);
        } elseif ($status === 'conference_scanned') {
            $query->where('scanned_conference', true);
        } else {
            $query->where('status', $status);
        }
    }

    if ($request->has('access_type') && $request->access_type !== '') {
        $query->where('access_type', $request->access_type);
    }

    if ($request->has('search') && $request->search !== '') {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
              ->orWhere('last_name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('company_name', 'like', "%{$search}%");
        });
    }

    $participants = $query->withCount('scans')
                          ->withMax('scans', 'created_at')
                          ->orderBy('created_at', 'desc')
                          ->paginate(20);

    // ✅ Map raw DB state → frontend status
    $participants->getCollection()->transform(function ($participant) {
        if ($participant->scanned_conference) {
            $participant->status = 'conference_scanned';
        } elseif ($participant->scanned_fair) {
            $participant->status = 'fair_scanned';
        }
        // Otherwise keep original status (pending/accepted/rejected)
        return $participant;
    });

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
                'message' => 'Error while accepting participant.',
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
                'message' => 'Error while rejecting participant.',
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

            // Store QR URL before deleting
            $qrUrl = $participant->qr_image;

            // Send deletion notification email BEFORE deleting the participant
            try {
                Mail::to($participant->email)->send(
                    new ParticipantAccessMail($participant, $qrUrl, 'deleted')
                );
            } catch (\Exception $e) {
                Log::warning('Failed to send deletion email to participant', [
                    'participant_id' => $participant->id,
                    'email' => $participant->email,
                    'error' => $e->getMessage(),
                ]);
            }

            // Delete participant record
            $participant->delete();

            Log::info('Participant deleted', [
                'participant_id' => $id,
                'email' => $participant->email,
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Participant deleted successfully.',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete participant', [
                'participant_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Error while deleting participant.',
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
                'message' => 'Error while generating badge.',
            ], 500);
        }
    }

    /**
     * Get dashboard statistics
     */

    public function getDashboardStats(): JsonResponse
    {
        // 1. Total Registrations & Trend
        $totalRegistrations = Participant::count();
        $last7DaysRegistrations = Participant::where('created_at', '>=', now()->subDays(7))->count();
        $previous7DaysRegistrations = Participant::whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])->count();
        
        $registrationGrowth = 0;
        if ($previous7DaysRegistrations > 0) {
            $registrationGrowth = (($last7DaysRegistrations - $previous7DaysRegistrations) / $previous7DaysRegistrations) * 100;
        } elseif ($last7DaysRegistrations > 0) {
            $registrationGrowth = 100; // 100% growth if previous was 0
        }

        // 2. Email Delivery (Approximation: Sent = Accepted + Rejected)
        $emailsSent = Participant::whereIn('status', ['accepted', 'rejected'])->count();
        // Assuming 99% delivery rate for demo purposes as we don't track hard bounces yet
        $emailSuccessRate = $emailsSent > 0 ? 99 : 0;

        // 3. Total Scans Today
        $scansToday = Scan::whereDate('scanned_at', today())->count();

        // 4. Active Scanners (Unique users in last 24h)
        $activeScanners = Scan::where('scanned_at', '>=', now()->subHours(24))
            ->distinct('scanner_user')
            ->count('scanner_user');

        // 5. Pending Approvals
        $pendingApprovals = Participant::where('status', 'pending')->count();

        // 6. Status Distribution
        $statusStats = Participant::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        // 7. Registration Trends (Daily & Weekly)
        $dailyRegistrations = Participant::select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        $weeklyRegistrations = Participant::select(DB::raw('YEARWEEK(created_at) as week'), DB::raw('count(*) as count'))
            ->where('created_at', '>=', now()->subWeeks(12))
            ->groupBy('week')
            ->orderBy('week', 'asc')
            ->get();

        // 8. Scan Volume (Fair vs Conference) - Last 30 Days
        $dailyScans = Scan::select(
                DB::raw('DATE(scanned_at) as date'), 
                'scan_type', 
                DB::raw('count(*) as count')
            )
            ->where('scanned_at', '>=', now()->subDays(30))
            ->groupBy('date', 'scan_type')
            ->orderBy('date', 'asc')
            ->get()
            ->groupBy('date');

        // 9. Top Scanned Participants
        $topScanned = Participant::withCount('scans')
            ->withMax('scans', 'scanned_at')
            ->orderBy('scans_count', 'desc')
            ->limit(10)
            ->get(['id', 'first_name', 'last_name', 'company_name', 'email']);

        // 10. Recent Live Scans
        $recentScans = Scan::with('participant:id,first_name,last_name,company_name')
            ->orderBy('scanned_at', 'desc')
            ->limit(10)
            ->get();

        // 11. System Health (Mocked for now)
        $systemHealth = [
            'disk_usage' => '12%', // Mock
            'qr_generation_rate' => '0.2s', // Mock
            'api_response_time' => '45ms', // Mock
            'status' => 'healthy'
        ];

        return response()->json([
            'ok' => true,
            'stats' => [
                'kpi' => [
                    'total_registrations' => $totalRegistrations,
                    'registration_growth' => round($registrationGrowth, 1),
                    'email_success_rate' => $emailSuccessRate,
                    'scans_today' => $scansToday,
                    'active_scanners' => $activeScanners,
                    'pending_approvals' => $pendingApprovals,
                ],
                'charts' => [
                    'status_distribution' => $statusStats,
                    'daily_registrations' => $dailyRegistrations,
                    'weekly_registrations' => $weeklyRegistrations,
                    'daily_scans' => $dailyScans,
                ],
                'top_participants' => $topScanned,
                'recent_scans' => $recentScans,
                'system_health' => $systemHealth,
            ]
        ]);
    }
}
