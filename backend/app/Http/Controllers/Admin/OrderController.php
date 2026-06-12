<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Organizer-scoped Orders list (NEW in Phase 1).
 *
 * Order already uses the Phase 0 BelongsToOrganizer trait, so Order::query() is
 * AUTO-SCOPED by the global scope to the authenticated organizer's rows (and is a
 * no-op for super-admin, who sees all orders). ZERO scoping logic lives here.
 */
class OrderController extends Controller
{
    /**
     * List the org's orders (paginated). Optional ?status= and ?search= (order_number/buyer email/name).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('buyer_email', 'like', "%{$search}%")
                  ->orWhere('buyer_first_name', 'like', "%{$search}%")
                  ->orWhere('buyer_last_name', 'like', "%{$search}%");
            });
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'ok' => true,
            'orders' => $orders,
        ]);
    }
}
