<?php

namespace App\Http\Controllers\Api\V1\Rider;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\RiderLocationPing;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function updateLocation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'recorded_at' => 'nullable|date',
            'accuracy_m' => 'nullable|numeric|min:0',
            'speed_mps' => 'nullable|numeric|min:0',
            'heading_deg' => 'nullable|numeric|min:0|max:360',
        ]);

        $profile = $request->user()->riderProfile;

        $recordedAt = isset($data['recorded_at'])
            ? CarbonImmutable::parse($data['recorded_at'])
            : now()->toImmutable();

        // Guard against far-future device times.
        if ($recordedAt->greaterThan(now()->addMinutes(10))) {
            $recordedAt = now()->toImmutable();
        }

        // Basic dedupe: ignore rapid duplicates near the last ping.
        $last = RiderLocationPing::query()
            ->where('rider_profile_id', $profile->id)
            ->latest('recorded_at')
            ->first();

        if ($last) {
            $seconds = $recordedAt->diffInSeconds($last->recorded_at);
            if ($seconds < 15) {
                $distM = $this->distanceMeters((float) $last->lat, (float) $last->lng, (float) $data['lat'], (float) $data['lng']);
                if ($distM < 20) {
                    // Still update current position for live map.
                    $profile->update([
                        'current_lat' => $data['lat'],
                        'current_lng' => $data['lng'],
                    ]);
                    return response()->json(['message' => 'Location updated.']);
                }
            }
        }

        RiderLocationPing::create([
            'rider_profile_id' => $profile->id,
            'lat' => $data['lat'],
            'lng' => $data['lng'],
            'accuracy_m' => $data['accuracy_m'] ?? null,
            'speed_mps' => $data['speed_mps'] ?? null,
            'heading_deg' => $data['heading_deg'] ?? null,
            'recorded_at' => $recordedAt,
        ]);

        $profile->update([
            'current_lat' => $data['lat'],
            'current_lng' => $data['lng'],
        ]);

        return response()->json(['message' => 'Location updated.']);
    }

    private function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000.0;
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $dPhi = deg2rad($lat2 - $lat1);
        $dLambda = deg2rad($lng2 - $lng1);

        $a = sin($dPhi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($dLambda / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $r * $c;
    }

    public function updateOnlineStatus(Request $request): JsonResponse
    {
        $data = $request->validate(['is_online' => 'required|boolean']);

        $request->user()->riderProfile->update(['is_online' => $data['is_online']]);

        return response()->json(['is_online' => $data['is_online']]);
    }

    public function earnings(Request $request): JsonResponse
    {
        $profile = $request->user()->riderProfile;

        return response()->json([
            'cash_in_hand' => $profile->cash_in_hand,
            'is_online' => $profile->is_online,
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('riderProfile.assignedCity');
        $profile = $user->riderProfile;
        $riderId = $user->id;
        $base = Order::query()->where('rider_id', $riderId);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
            'profile' => [
                'is_online' => $profile->is_online,
                'cash_in_hand' => $profile->cash_in_hand,
                'documents_verified' => $profile->documents_verified,
                'assigned_city' => $profile->assignedCity?->name,
            ],
            'stats' => [
                'all' => (clone $base)->count(),
                'delivered' => (clone $base)->where('order_status', OrderStatus::Delivered)->count(),
                'pending' => (clone $base)->whereIn('order_status', [
                    OrderStatus::ReadyToShip,
                    OrderStatus::Dispatched,
                ])->count(),
                'in_process' => (clone $base)->where('order_status', OrderStatus::PickedUp)->count(),
                'returned' => (clone $base)->where('order_status', OrderStatus::Cancelled)->count(),
            ],
        ]);
    }
}
