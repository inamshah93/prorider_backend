<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\AuditLogService;
use App\Services\OrderNotificationService;
use App\Services\OrderStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private OrderStateMachine $stateMachine,
        private AuditLogService $auditLog,
        private OrderNotificationService $notifications,
    ) {}

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
            'data' => new OrderResource($order->load([
                'merchant.user',
                'rider',
                'targetCity',
                'events.actor',
                'customerUser',
            ])),
        ]);
    }

    public function assignRider(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate(['rider_id' => 'required|exists:users,id']);

        $order->update(['rider_id' => $data['rider_id']]);

        if ($order->order_status === OrderStatus::ReadyToShip) {
            $this->stateMachine->transition($order, OrderStatus::Dispatched, $request->user());
        }

        $fresh = $order->fresh()->load(['rider', 'merchant']);

        $this->auditLog->record(
            user: $request->user(),
            action: 'order.assign_rider',
            entity: 'order',
            entityId: $order->id,
            message: "Assigned rider #{$data['rider_id']} to {$fresh->order_reference_number}",
            context: ['rider_id' => $data['rider_id']],
            request: $request,
        );

        $this->notifications->notifyRiderAssigned($fresh);

        return response()->json(['data' => new OrderResource($fresh)]);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        $this->stateMachine->transition($order, OrderStatus::Cancelled, $request->user());

        $this->auditLog->record(
            user: $request->user(),
            action: 'order.cancel',
            entity: 'order',
            entityId: $order->id,
            message: "Cancelled order {$order->order_reference_number}",
            request: $request,
        );

        return response()->json(['data' => new OrderResource($order->fresh())]);
    }
}
