<?php

namespace App\Http\Controllers\Api\V1\Rider;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderHistoryController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $riderId = $request->user()->id;
        $base = Order::query()->where('rider_id', $riderId);

        return response()->json([
            'all' => (clone $base)->count(),
            'delivered' => (clone $base)->where('order_status', OrderStatus::Delivered)->count(),
            'pending' => (clone $base)->whereIn('order_status', [
                OrderStatus::ReadyToShip,
                OrderStatus::Dispatched,
            ])->count(),
            'in_process' => (clone $base)->where('order_status', OrderStatus::PickedUp)->count(),
            'returned' => (clone $base)->where('order_status', OrderStatus::Cancelled)->count(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $filter = $request->query('filter', 'all');
        $riderId = $request->user()->id;

        $query = Order::with(['merchant', 'targetCity'])
            ->where('rider_id', $riderId)
            ->latest();

        $query = match ($filter) {
            'delivered' => $query->where('order_status', OrderStatus::Delivered),
            'pending' => $query->whereIn('order_status', [
                OrderStatus::ReadyToShip,
                OrderStatus::Dispatched,
            ]),
            'in_process' => $query->where('order_status', OrderStatus::PickedUp),
            'returned' => $query->where('order_status', OrderStatus::Cancelled),
            default => $query,
        };

        return response()->json(['data' => OrderResource::collection($query->get())]);
    }
}
