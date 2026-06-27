<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\FinancialLedger;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $merchant = $request->user()->merchant;

        $deliveredToday = Order::where('merchant_id', $merchant->id)
            ->where('order_status', OrderStatus::Delivered)
            ->whereDate('updated_at', today())
            ->count();

        $payables = FinancialLedger::where('merchant_id', $merchant->id)
            ->where('entry_type', 'merchant_payable')
            ->sum('amount');

        $recentOrders = Order::where('merchant_id', $merchant->id)
            ->latest()
            ->limit(5)
            ->get();

        return response()->json([
            'delivered_today' => $deliveredToday,
            'account_payables' => $payables,
            'recent_orders' => OrderResource::collection($recentOrders),
        ]);
    }
}
