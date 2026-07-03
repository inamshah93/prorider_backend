<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\EtaCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function track(Request $request, string $orderReference): JsonResponse
    {
        $request->validate(['phone' => 'nullable|string']);

        $order = Order::with(['rider.riderProfile', 'targetCity', 'events', 'merchant'])
            ->where('order_reference_number', $orderReference)
            ->when($request->phone, fn ($q) => $q->where('customer_phone', $request->phone))
            ->firstOrFail();

        $milestones = $this->buildMilestones($order);

        $riderLocation = null;
        $etaMinutes = null;
        if ($order->rider?->riderProfile) {
            $profile = $order->rider->riderProfile;
            $riderLocation = [
                'lat' => $profile->current_lat,
                'lng' => $profile->current_lng,
                'rider_name' => $order->rider->name,
                'rider_phone' => $order->order_status?->value === 'picked_up' ? $order->rider->phone : null,
            ];

            $etaMinutes = EtaCalculator::minutesBetween(
                $profile->current_lat ? (float) $profile->current_lat : null,
                $profile->current_lng ? (float) $profile->current_lng : null,
                $order->pickup_lat ? (float) $order->pickup_lat : null,
                $order->pickup_lng ? (float) $order->pickup_lng : null,
            );
        }

        return response()->json([
            'order_reference' => $order->order_reference_number,
            'order_status' => $order->order_status?->value ?? $order->order_status,
            'customer_name' => $order->customer_name,
            'delivery_address' => $order->delivery_address,
            'target_city' => $order->targetCity?->name,
            'merchant' => $order->merchant ? [
                'store_name' => $order->merchant->store_name,
            ] : null,
            'milestones' => $milestones,
            'rider_location' => $riderLocation,
            'pickup_location' => ($order->pickup_lat && $order->pickup_lng) ? [
                'lat' => $order->pickup_lat,
                'lng' => $order->pickup_lng,
            ] : null,
            'eta_minutes' => $etaMinutes,
            'can_rate' => ($order->order_status?->value ?? $order->order_status) === 'delivered',
            'updated_at' => $order->updated_at,
        ]);
    }

    private function buildMilestones(Order $order): array
    {
        $status = $order->order_status?->value ?? $order->order_status;

        $steps = [
            ['key' => 'processed', 'label' => 'Order Processed', 'active' => true, 'completed' => true],
            ['key' => 'dispatched', 'label' => 'Dispatched', 'active' => false, 'completed' => false],
            ['key' => 'out_for_delivery', 'label' => 'Out for Delivery', 'active' => false, 'completed' => false],
            ['key' => 'rider_arrived', 'label' => 'Rider Arrived', 'active' => false, 'completed' => false],
        ];

        $statusMap = [
            'created' => 0,
            'ready_to_ship' => 0,
            'dispatched' => 1,
            'picked_up' => 2,
            'delivered' => 3,
            'cancelled' => -1,
            'failed' => 2,
            'returned' => -1,
        ];

        $level = $statusMap[$status] ?? 0;

        foreach ($steps as $i => &$step) {
            $step['completed'] = $i < $level;
            $step['active'] = $i === $level;
        }

        if ($status === 'delivered') {
            foreach ($steps as &$step) {
                $step['completed'] = true;
                $step['active'] = false;
            }
            $steps[3]['active'] = true;
        }

        return $steps;
    }
}
