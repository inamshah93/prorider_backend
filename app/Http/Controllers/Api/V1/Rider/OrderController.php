<?php

namespace App\Http\Controllers\Api\V1\Rider;

use App\Enums\OrderStatus;
use App\Events\OrderDelivered;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderNotificationService;
use App\Services\OrderStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private OrderStateMachine $stateMachine,
        private OrderNotificationService $notifications,
    ) {}

    public function batchPickedUp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_ids' => 'required|array|min:1|max:10',
            'order_ids.*' => 'integer|exists:orders,id',
        ]);

        $results = [];
        foreach ($data['order_ids'] as $orderId) {
            $order = Order::findOrFail($orderId);
            $results[] = $this->performPickup($request, $order);
        }

        return response()->json(['data' => $results]);
    }

    public function pickedUp(Request $request, Order $order): JsonResponse
    {
        return response()->json(['data' => $this->performPickup($request, $order)]);
    }

    public function delivered(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->rider_id === $request->user()->id, 403);

        $this->stateMachine->transition($order, OrderStatus::Delivered, $request->user());
        $fresh = $order->fresh(['merchant', 'customerUser']);
        event(new OrderDelivered($fresh));

        $this->notifications->notifyCustomerStatus(
            $fresh,
            'Order delivered',
            "Your order {$fresh->order_reference_number} has been delivered.",
        );
        $this->notifications->notifyMerchant(
            $fresh,
            'Order delivered',
            "Order {$fresh->order_reference_number} was delivered.",
        );

        return response()->json(['data' => new OrderResource($fresh)]);
    }

    public function checkout(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->rider_id === $request->user()->id, 403);

        $payload = [
            'order_reference' => $order->order_reference_number,
            'cod_amount' => $order->cod_amount,
            'delivery_charge' => $order->delivery_charge,
            'payment_method' => $order->payment_method?->value ?? $order->payment_method,
            'terms' => 'Collect cash from customer upon delivery.',
        ];

        if (($order->payment_method?->value ?? $order->payment_method) === 'bank_transfer') {
            $payload['bank_transfer'] = [
                'account_title' => 'Velo Logistics',
                'account_number' => '1234567890',
                'iban' => 'PK00VELO0000001234567890',
                'qr_payload' => "velo://pay?ref={$order->order_reference_number}&amount={$order->cod_amount}",
            ];
        }

        return response()->json(['data' => $payload]);
    }

    private function performPickup(Request $request, Order $order): OrderResource
    {
        if (! $order->rider_id) {
            $order->update(['rider_id' => $request->user()->id]);
            if ($order->order_status === OrderStatus::ReadyToShip) {
                $this->stateMachine->transition($order, OrderStatus::Dispatched, $request->user());
                $order->refresh();
            }
        }

        abort_unless($order->rider_id === $request->user()->id, 403);

        $this->stateMachine->transition($order, OrderStatus::PickedUp, $request->user());
        $fresh = $order->fresh(['merchant', 'targetCity', 'customerUser']);

        $this->notifications->notifyCustomerStatus(
            $fresh,
            'Out for delivery',
            "Your order {$fresh->order_reference_number} is on the way.",
        );

        return new OrderResource($fresh);
    }
}
