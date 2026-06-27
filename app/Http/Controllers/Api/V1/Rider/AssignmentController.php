<?php

namespace App\Http\Controllers\Api\V1\Rider;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $riderId = $request->user()->id;
        $cityId = $request->user()->riderProfile?->assigned_city_id;

        $orders = Order::with(['merchant', 'targetCity'])
            ->where(function ($q) use ($riderId, $cityId) {
                $q->where('rider_id', $riderId)
                    ->orWhere(function ($q2) use ($cityId) {
                        $q2->where('order_status', OrderStatus::ReadyToShip)
                            ->when($cityId, fn ($q3) => $q3->where('target_city_id', $cityId));
                    });
            })
            ->latest()
            ->get();

        return response()->json(['data' => OrderResource::collection($orders)]);
    }
}
