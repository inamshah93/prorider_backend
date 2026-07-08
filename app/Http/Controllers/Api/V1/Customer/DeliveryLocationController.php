<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerOrderResource;
use App\Models\Order;
use App\Services\CustomerDeliveryLocationService;
use App\Support\AppRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryLocationController extends Controller
{
    public function __construct(private CustomerDeliveryLocationService $deliveryLocations) {}

    public function update(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('Customer')) {
            return response()->json([
                'message' => AppRole::accessDeniedMessage(AppRole::CUSTOMER),
            ], 403);
        }

        abort_unless($this->ownsOrder($user, $order), 403);

        $data = $request->validate([
            'delivery_lat' => 'required|numeric|between:-90,90',
            'delivery_lng' => 'required|numeric|between:-180,180',
            'delivery_address' => 'nullable|string|max:1000',
        ]);

        $order = $this->deliveryLocations->updateLocation(
            $order,
            $user,
            (float) $data['delivery_lat'],
            (float) $data['delivery_lng'],
            $data['delivery_address'] ?? null,
        );

        $order->load(['merchant', 'targetCity']);

        return response()->json([
            'message' => 'Delivery location updated.',
            'data' => new CustomerOrderResource($order),
        ]);
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('Customer')) {
            return response()->json([
                'message' => AppRole::accessDeniedMessage(AppRole::CUSTOMER),
            ], 403);
        }

        $data = $request->validate([
            'delivery_lat' => 'required|numeric|between:-90,90',
            'delivery_lng' => 'required|numeric|between:-180,180',
            'delivery_address' => 'nullable|string|max:1000',
        ]);

        $result = $this->deliveryLocations->bulkUpdateLocations(
            $user,
            (float) $data['delivery_lat'],
            (float) $data['delivery_lng'],
            $data['delivery_address'] ?? null,
        );

        $orders = $result['orders'];
        foreach ($orders as $order) {
            $order->load(['merchant', 'targetCity']);
        }

        return response()->json([
            'message' => "Delivery location set for {$result['updated_count']} order(s).",
            'updated_count' => $result['updated_count'],
            'data' => CustomerOrderResource::collection($orders),
        ]);
    }

    private function ownsOrder($user, Order $order): bool
    {
        if ($order->customer_user_id === $user->id) {
            return true;
        }

        return $user->phone && $order->customer_phone === $user->phone;
    }
}
