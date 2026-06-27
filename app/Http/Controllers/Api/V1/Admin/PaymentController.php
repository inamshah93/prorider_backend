<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentOverrideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private PaymentOverrideService $overrideService) {}

    public function pending(): JsonResponse
    {
        $orders = Order::where('payment_status', PaymentStatus::Pending)
            ->with(['merchant'])
            ->latest()
            ->paginate(20);

        return response()->json(['data' => $orders]);
    }

    public function override(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'new_status' => 'required|in:pending,paid,failed',
            'reason' => 'required|string|min:5',
        ]);

        $order = Order::findOrFail($data['order_id']);
        $override = $this->overrideService->override(
            $order,
            $request->user(),
            PaymentStatus::from($data['new_status']),
            $data['reason'],
        );

        return response()->json(['data' => $override]);
    }
}
