<?php

namespace App\Http\Controllers\Api\V1\Rider;

use App\Enums\OrderStatus;
use App\Events\OrderDelivered;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private OrderStateMachine $stateMachine) {}

    public function pickedUp(Request $request, Order $order): JsonResponse
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

        return response()->json(['data' => new OrderResource($order->fresh())]);
    }

    public function delivered(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->rider_id === $request->user()->id, 403);

        $this->stateMachine->transition($order, OrderStatus::Delivered, $request->user());
        event(new OrderDelivered($order->fresh()));

        return response()->json(['data' => new OrderResource($order->fresh())]);
    }

    public function checkout(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->rider_id === $request->user()->id, 403);

        $payload = [
            'order_reference' => $order->order_reference_number,
            'cod_amount' => $order->cod_amount,
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
}
