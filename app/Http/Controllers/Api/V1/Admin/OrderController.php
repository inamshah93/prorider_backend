<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private OrderStateMachine $stateMachine) {}

    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['merchant', 'rider', 'targetCity']);

        if ($status = $request->get('status')) {
            $query->where('order_status', $status);
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_reference_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        $orders = $query->latest()->paginate(20);

        return response()->json([
            'data' => OrderResource::collection($orders),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json([
            'data' => new OrderResource($order->load(['merchant', 'rider', 'targetCity', 'events.actor'])),
        ]);
    }

    public function assignRider(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate(['rider_id' => 'required|exists:users,id']);

        $order->update(['rider_id' => $data['rider_id']]);

        if ($order->order_status === OrderStatus::ReadyToShip) {
            $this->stateMachine->transition($order, OrderStatus::Dispatched, $request->user());
        }

        return response()->json(['data' => new OrderResource($order->fresh()->load(['rider', 'merchant']))]);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        $this->stateMachine->transition($order, OrderStatus::Cancelled, $request->user());

        return response()->json(['data' => new OrderResource($order->fresh())]);
    }
}
