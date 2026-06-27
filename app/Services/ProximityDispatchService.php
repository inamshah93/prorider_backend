<?php

namespace App\Services;

use App\Jobs\SendFcmNotificationJob;
use App\Models\Order;
use App\Models\RiderProfile;
use Illuminate\Support\Collection;

class ProximityDispatchService
{
    public function dispatchToNearestRiders(Order $order, int $limit = 5): Collection
    {
        $pickupLat = $order->pickup_lat;
        $pickupLng = $order->pickup_lng;

        $query = RiderProfile::query()
            ->with('user')
            ->where('is_online', true)
            ->where('documents_verified', true)
            ->whereHas('user', fn ($q) => $q->where('status', 'active')->whereNotNull('device_token'));

        if ($order->target_city_id) {
            $query->where('assigned_city_id', $order->target_city_id);
        }

        $riders = $query->get();

        if ($pickupLat && $pickupLng) {
            $riders = $riders->sortBy(fn (RiderProfile $r) => $this->distance(
                (float) $pickupLat,
                (float) $pickupLng,
                (float) ($r->current_lat ?? 0),
                (float) ($r->current_lng ?? 0),
            ));
        }

        $selected = $riders->take($limit);

        foreach ($selected as $rider) {
            if ($rider->user?->device_token) {
                SendFcmNotificationJob::dispatch(
                    $rider->user->device_token,
                    'New delivery available',
                    "Order {$order->order_reference_number} is ready to ship.",
                    ['order_id' => (string) $order->id, 'type' => 'new_assignment'],
                );
            }
        }

        return $selected;
    }

    private function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        if ($lat2 == 0.0 && $lng2 == 0.0) {
            return PHP_FLOAT_MAX;
        }

        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
