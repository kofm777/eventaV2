<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Organizer;
use App\Models\Payout;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SUPER-ADMIN ONLY platform control plane (Phase 5).
 *
 * Gated by role:superadmin at the route (NOT by organizer.active). A super-admin
 * bypasses the BelongsToOrganizer global scope (OrganizerContext::hasTenantScope()
 * is false for them), so a plain Order::query() naturally aggregates EVERY organizer.
 *
 * Single source of truth: this controller READS the Phase 3 capture columns
 * (orders.platform_fee / organizer_amount, set at the PAID moment) — it never
 * recomputes commission. The default currency is single-currency TND.
 */
class PlatformController extends Controller
{
    private const CURRENCY = 'TND';

    /**
     * GET /admin/platform/analytics — platform-wide totals + per-organizer breakdown.
     */
    public function analytics(Request $request): JsonResponse
    {
        $paid = fn () => Order::query()->where('status', Order::STATUS_PAID);

        $grossSales = (float) $paid()->sum('amount_total');
        $commissionEarned = (float) $paid()->sum('platform_fee');
        $organizerGross = (float) $paid()->sum('organizer_amount');
        $payoutsPaid = (float) Payout::where('status', Payout::STATUS_COMPLETED)->sum('amount');
        $payoutsOwed = round($organizerGross - $payoutsPaid, 2);

        // Issued seats = VALID + CHECKED_IN tickets (matches what was physically issued).
        $ticketCount = Ticket::whereIn('status', [Ticket::STATUS_VALID, Ticket::STATUS_CHECKED_IN])->count();

        // Order counts by status (every status present, zero-filled).
        $rawCounts = Order::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $orderCounts = [];
        foreach ([
            Order::STATUS_PENDING_PAYMENT,
            Order::STATUS_PAID,
            Order::STATUS_FAILED,
            Order::STATUS_CANCELLED,
            Order::STATUS_REFUNDED,
        ] as $status) {
            $orderCounts[$status] = (int) ($rawCounts[$status] ?? 0);
        }

        $organizerCount = Organizer::count();
        $activeOrganizerCount = Organizer::where('status', Organizer::STATUS_ACTIVE)->count();

        return response()->json([
            'ok' => true,
            'analytics' => [
                'totals' => [
                    'gross_sales' => round($grossSales, 2),
                    'commission_earned' => round($commissionEarned, 2),
                    'organizer_gross' => round($organizerGross, 2),
                    'payouts_paid' => round($payoutsPaid, 2),
                    'payouts_owed' => $payoutsOwed,
                    'ticket_count' => $ticketCount,
                    'order_counts' => $orderCounts,
                    'organizer_count' => $organizerCount,
                    'active_organizer_count' => $activeOrganizerCount,
                    'currency' => self::CURRENCY,
                ],
                'organizers' => $this->organizerBreakdown(),
            ],
        ]);
    }

    /**
     * GET /admin/platform/balances — per-organizer outstanding balance list.
     * Reuses the same grouped breakdown as analytics() (small organizer count).
     */
    public function balances(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'balances' => $this->organizerBreakdown(),
            'currency' => self::CURRENCY,
        ]);
    }

    /**
     * POST /admin/organizers/{id}/payouts — record a manual payout ledger entry.
     *
     * Tunisian gateways have no auto-split, so payouts are manual entries. Stamps
     * created_by from the acting super-admin; status defaults to completed. By default
     * rejects an over-payout (amount > current balance) with 422 unless ?allow_overdraw
     * is set, so the owner can still override when needed.
     */
    public function recordPayout(int $id, Request $request): JsonResponse
    {
        $organizer = Organizer::find($id);

        if (! $organizer) {
            return response()->json([
                'ok' => false,
                'message' => 'Organizer not found.',
            ], 404);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|size:3',
            'period' => 'nullable|string|max:50',
            'note' => 'nullable|string',
        ]);

        $amount = round((float) $validated['amount'], 2);
        $balance = $organizer->balance();
        $allowOverdraw = $request->boolean('allow_overdraw');

        if (! $allowOverdraw && $amount > $balance) {
            return response()->json([
                'ok' => false,
                'message' => 'Payout exceeds outstanding balance.',
                'balance' => $balance,
            ], 422);
        }

        $payout = DB::transaction(function () use ($organizer, $validated, $amount, $request) {
            return Payout::create([
                'organizer_id' => $organizer->id,
                'amount' => $amount,
                'currency' => strtoupper($validated['currency'] ?? self::CURRENCY),
                'status' => Payout::STATUS_COMPLETED,
                'period' => $validated['period'] ?? null,
                'note' => $validated['note'] ?? null,
                'created_by' => $request->user('sanctum')?->id,
            ]);
        });

        Log::info('Manual payout recorded', [
            'organizer_id' => $organizer->id,
            'payout_id' => $payout->id,
            'amount' => $amount,
        ]);

        return response()->json([
            'ok' => true,
            'payout' => $payout,
            'balance' => $organizer->fresh()->balance(),
            'message' => 'Payout recorded.',
        ], 201);
    }

    /**
     * GET /admin/organizers/{id}/payouts — one organizer's payout history.
     */
    public function payoutHistory(int $id, Request $request): JsonResponse
    {
        $organizer = Organizer::find($id);

        if (! $organizer) {
            return response()->json([
                'ok' => false,
                'message' => 'Organizer not found.',
            ], 404);
        }

        $payouts = $organizer->payouts()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'ok' => true,
            'payouts' => $payouts,
            'balance' => $organizer->balance(),
        ]);
    }

    /**
     * Shared per-organizer breakdown used by analytics() + balances(). One grouped
     * PAID-orders query keyed onto the full Organizer list (LEFT join semantics) so
     * organizers with zero PAID orders still appear, each enriched with payouts_total
     * and balance. Reads ONLY captured columns — no recomputation.
     *
     * @return array<int, array<string, mixed>>
     */
    private function organizerBreakdown(): array
    {
        // Grouped PAID-order aggregates per organizer.
        $orderAgg = Order::query()
            ->where('status', Order::STATUS_PAID)
            ->select(
                'organizer_id',
                DB::raw('SUM(amount_total) as gross_sales'),
                DB::raw('SUM(platform_fee) as commission_earned'),
                DB::raw('SUM(organizer_amount) as organizer_amount'),
                DB::raw('COUNT(*) as paid_orders'),
                DB::raw('SUM(quantity) as tickets')
            )
            ->groupBy('organizer_id')
            ->get()
            ->keyBy('organizer_id');

        // Completed payouts per organizer.
        $payoutAgg = Payout::query()
            ->where('status', Payout::STATUS_COMPLETED)
            ->select('organizer_id', DB::raw('SUM(amount) as payouts_total'))
            ->groupBy('organizer_id')
            ->pluck('payouts_total', 'organizer_id');

        $defaultRate = (float) config('services.payments.commission_rate', 0);

        return Organizer::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'status', 'commission_rate'])
            ->map(function (Organizer $org) use ($orderAgg, $payoutAgg, $defaultRate) {
                $agg = $orderAgg->get($org->id);

                $organizerAmount = round((float) ($agg->organizer_amount ?? 0), 2);
                $payoutsTotal = round((float) ($payoutAgg[$org->id] ?? 0), 2);

                $effectiveRate = $org->commission_rate !== null
                    ? (float) $org->commission_rate
                    : $defaultRate;

                return [
                    'organizer_id' => $org->id,
                    'name' => $org->name,
                    'slug' => $org->slug,
                    'status' => $org->status,
                    'commission_rate' => $org->commission_rate !== null ? (float) $org->commission_rate : null,
                    'effective_commission_rate' => $effectiveRate,
                    'gross_sales' => round((float) ($agg->gross_sales ?? 0), 2),
                    'commission_earned' => round((float) ($agg->commission_earned ?? 0), 2),
                    'organizer_amount' => $organizerAmount,
                    'payouts_total' => $payoutsTotal,
                    'balance' => round($organizerAmount - $payoutsTotal, 2),
                    'paid_orders' => (int) ($agg->paid_orders ?? 0),
                    'tickets' => (int) ($agg->tickets ?? 0),
                ];
            })
            ->all();
    }
}
