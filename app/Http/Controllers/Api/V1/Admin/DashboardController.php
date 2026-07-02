<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\FinancialLedger;
use App\Models\Order;
use App\Models\RiderProfile;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $activeRiders = RiderProfile::where('is_online', true)->count();
        $liveOrders = Order::whereNotIn('order_status', [OrderStatus::Delivered, OrderStatus::Cancelled])->count();
        $codPending = Order::where('payment_status', PaymentStatus::Pending)
            ->where('payment_method', 'cod')
            ->sum('cod_amount');
        $bankApprovals = Order::where('payment_method', 'bank_transfer')
            ->where('payment_status', PaymentStatus::Pending)
            ->count();

        $riderCashPayable = RiderProfile::sum('cash_in_hand');

        $merchantPayables = FinancialLedger::query()
            ->where('entry_type', 'merchant_payable')
            ->sum('amount');

        $platformFees = FinancialLedger::query()
            ->where('entry_type', 'platform_fee')
            ->sum('amount');

        $riderCommissions = FinancialLedger::query()
            ->where('entry_type', 'rider_commission')
            ->sum('amount');

        $netProfit = (float) $platformFees - (float) $riderCommissions;

        $recentActivity = Order::with(['merchant', 'targetCity'])
            ->latest()
            ->limit(10)
            ->get();

        $chartData = Order::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(14))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'metrics' => [
                'active_riders' => $activeRiders,
                'live_orders' => $liveOrders,
                'cod_pending' => $codPending,
                'bank_approvals' => $bankApprovals,
                'rider_payable_cash' => $riderCashPayable,
                'merchant_payable_cash' => $merchantPayables,
                'platform_fees' => $platformFees,
                'rider_commissions' => $riderCommissions,
                'net_profit' => $netProfit,
            ],
            'recent_activity' => OrderResource::collection($recentActivity),
            'chart_data' => $chartData,
        ]);
    }
}
