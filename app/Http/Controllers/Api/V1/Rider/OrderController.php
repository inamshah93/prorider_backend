<?php

namespace App\Http\Controllers\Api\V1\Rider;

use App\Enums\AssignmentStatus;
use App\Enums\OrderStatus;
use App\Events\OrderDelivered;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderNotificationService;
use App\Services\OrderStateMachine;
use App\Services\SmsNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private OrderStateMachine $stateMachine,
        private OrderNotificationService $notifications,
        private SmsNotificationService $sms,
    ) {}

    public function accept(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->rider_id === $request->user()->id, 403);
        abort_unless($order->assignment_status === AssignmentStatus::Pending->value, 422, 'Assignment is not pending.');

        $order->update(['assignment_status' => AssignmentStatus::Accepted->value]);

        return response()->json(['data' => new OrderResource($order->fresh(['merchant', 'targetCity']))]);
    }

    public function reject(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate(['reason' => 'nullable|string|max:500']);

        abort_unless($order->rider_id === $request->user()->id, 403);
        abort_unless($order->assignment_status === AssignmentStatus::Pending->value, 422, 'Assignment is not pending.');

        $order->update([
            'assignment_status' => AssignmentStatus::Rejected->value,
            'rider_id' => null,
            'failure_reason' => $data['reason'] ?? 'Rider rejected assignment',
        ]);

        return response()->json(['data' => new OrderResource($order->fresh())]);
    }

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

    public function failed(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:1000']);

        abort_unless($order->rider_id === $request->user()->id, 403);

        $this->stateMachine->transition($order, OrderStatus::Failed, $request->user(), [
            'reason' => $data['reason'],
        ]);

        $order->update([
            'failure_reason' => $data['reason'],
            'failed_at' => now(),
        ]);

        $fresh = $order->fresh(['merchant', 'customerUser']);
        $this->notifications->notifyCustomerStatus(
            $fresh,
            'Delivery attempt failed',
            "We could not deliver order {$fresh->order_reference_number}. Reason: {$data['reason']}",
        );
        $this->sms->sendOrderUpdate($fresh, 'Delivery attempt failed for your order.');

        return response()->json(['data' => new OrderResource($fresh)]);
    }

    public function markReturned(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->rider_id === $request->user()->id, 403);
        abort_unless($order->order_status === OrderStatus::Failed, 422, 'Order must be in failed status.');

        $this->stateMachine->transition($order, OrderStatus::Returned, $request->user());

        $fresh = $order->fresh(['merchant']);
        $this->notifications->notifyMerchant(
            $fresh,
            'Order returned (RTO)',
            "Order {$fresh->order_reference_number} was returned to hub.",
        );

        return response()->json(['data' => new OrderResource($fresh)]);
    }

    public function delivered(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->rider_id === $request->user()->id, 403);

        $data = $request->validate([
            'pod_photo' => 'nullable|image|max:5120',
            'signature' => 'nullable|image|max:2048',
        ]);

        $updates = [];
        if ($request->hasFile('pod_photo')) {
            $updates['pod_photo_path'] = $request->file('pod_photo')->store('pod', 'public');
        }
        if ($request->hasFile('signature')) {
            $updates['signature_path'] = $request->file('signature')->store('pod', 'public');
        }
        if ($updates) {
            $order->update($updates);
        }

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
        $this->sms->sendOrderUpdate($fresh, 'Your order has been delivered.');

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
            $order->update([
                'rider_id' => $request->user()->id,
                'assignment_status' => AssignmentStatus::Accepted->value,
            ]);
            if ($order->order_status === OrderStatus::ReadyToShip) {
                $this->stateMachine->transition($order, OrderStatus::Dispatched, $request->user());
                $order->refresh();
            }
        }

        abort_unless($order->rider_id === $request->user()->id, 403);

        if ($order->assignment_status === AssignmentStatus::Pending->value) {
            abort(422, 'Accept the assignment before pickup.');
        }

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
