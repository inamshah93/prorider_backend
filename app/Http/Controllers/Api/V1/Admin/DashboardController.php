<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CityResource;
use App\Http\Resources\MerchantResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\RiderProfileResource;
use App\Http\Resources\UserResource;
use App\Models\City;
use App\Models\CityAlias;
use App\Models\FinancialLedger;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\RiderProfile;
use App\Models\User;
use App\Services\OrderStateMachine;
use App\Services\PaymentOverrideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
            ],
            'recent_activity' => OrderResource::collection($recentActivity),
            'chart_data' => $chartData,
        ]);
    }
}
