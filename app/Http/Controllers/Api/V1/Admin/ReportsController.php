<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\DayEndSnapshot;
use App\Models\FinancialLedger;
use App\Models\RiderProfile;
use App\Models\RiderSettlement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function dayEnd(Request $request): JsonResponse
    {
        $limit = (int) ($request->get('limit') ?? 14);
        $limit = max(1, min($limit, 60));

        $snapshots = DayEndSnapshot::query()
            ->orderByDesc('snapshot_date')
            ->limit($limit)
            ->get();

        return response()->json(['data' => $snapshots]);
    }

    public function riders(): JsonResponse
    {
        $riders = User::role('Rider')->with('riderProfile')->get();

        $data = $riders->map(function (User $r) {
            $riderId = $r->id;

            $totalCollected = (float) FinancialLedger::query()
                ->where('rider_id', $riderId)
                ->where('entry_type', 'cod_collected')
                ->sum('amount');

            $totalCommission = (float) FinancialLedger::query()
                ->where('rider_id', $riderId)
                ->where('entry_type', 'rider_commission')
                ->sum('amount');

            $totalSettled = (float) RiderSettlement::query()
                ->where('rider_id', $riderId)
                ->sum('amount');

            return [
                'id' => $r->riderProfile?->id ?? $riderId,
                'user_id' => $riderId,
                'name' => $r->name,
                'phone' => $r->phone,
                'is_online' => (bool) ($r->riderProfile?->is_online ?? false),
                'cash_in_hand' => (float) ($r->riderProfile?->cash_in_hand ?? 0),
                'total_collected' => $totalCollected,
                'total_commission_earned' => $totalCommission,
                'total_settled' => $totalSettled,
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function analytics(): JsonResponse
    {
        $byStatus = \App\Models\Order::query()
            ->selectRaw('order_status as status, COUNT(*) as count')
            ->groupBy('order_status')
            ->pluck('count', 'status');

        $deliveredToday = \App\Models\Order::where('order_status', 'delivered')
            ->whereDate('updated_at', today())
            ->count();

        $failedWeek = \App\Models\Order::where('order_status', 'failed')
            ->where('updated_at', '>=', now()->subDays(7))
            ->count();

        $returnedWeek = \App\Models\Order::where('order_status', 'returned')
            ->where('updated_at', '>=', now()->subDays(7))
            ->count();

        $avgRating = \App\Models\DeliveryRating::avg('score');

        return response()->json([
            'data' => [
                'orders_by_status' => $byStatus,
                'delivered_today' => $deliveredToday,
                'failed_last_7_days' => $failedWeek,
                'returned_last_7_days' => $returnedWeek,
                'average_delivery_rating' => $avgRating ? round((float) $avgRating, 2) : null,
            ],
        ]);
    }
}

