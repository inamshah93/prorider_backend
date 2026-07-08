<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CustomerDeliveryLocationService
{
    /** @var list<string> */
    private array $lockedStatuses = [
        OrderStatus::Delivered->value,
        OrderStatus::Cancelled->value,
        OrderStatus::Returned->value,
    ];

    public function canUpdateLocation(Order $order): bool
    {
        $status = $order->order_status?->value ?? $order->order_status;

        return ! in_array($status, $this->lockedStatuses, true);
    }

    public function updateLocation(Order $order, User $actor, float $lat, float $lng, ?string $address = null): Order
    {
        if (! $this->canUpdateLocation($order)) {
            throw ValidationException::withMessages([
                'order' => ['Delivery location cannot be changed after the order is delivered or closed.'],
            ]);
        }

        $previous = [
            'delivery_lat' => $order->delivery_lat,
            'delivery_lng' => $order->delivery_lng,
            'delivery_address' => $order->delivery_address,
        ];

        $order->delivery_lat = $lat;
        $order->delivery_lng = $lng;

        if ($address !== null && trim($address) !== '') {
            $order->delivery_address = trim($address);
        }

        $order->save();

        OrderEvent::create([
            'order_id' => $order->id,
            'actor_id' => $actor->id,
            'event_type' => 'delivery_location_updated',
            'from_status' => $order->order_status?->value ?? $order->order_status,
            'to_status' => $order->order_status?->value ?? $order->order_status,
            'metadata' => [
                'previous' => $previous,
                'delivery_lat' => $lat,
                'delivery_lng' => $lng,
                'delivery_address' => $order->delivery_address,
            ],
        ]);

        return $order->fresh();
    }

    /**
     * @return array{updated_count: int, orders: Collection<int, Order>}
     */
    public function bulkUpdateLocations(User $user, float $lat, float $lng, ?string $address = null): array
    {
        $orders = CustomerOrderLinkService::ordersForUserQuery($user)
            ->whereNotIn('order_status', $this->lockedStatuses)
            ->get();

        if ($orders->isEmpty()) {
            throw ValidationException::withMessages([
                'orders' => ['No open orders found to update.'],
            ]);
        }

        $updated = collect();

        foreach ($orders as $order) {
            $updated->push($this->updateLocation($order, $user, $lat, $lng, $address));
        }

        return [
            'updated_count' => $updated->count(),
            'orders' => $updated,
        ];
    }
}
