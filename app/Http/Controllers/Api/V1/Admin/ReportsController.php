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
                'id' => $riderId,
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
}

