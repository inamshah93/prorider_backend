<?php

namespace App\Http\Controllers\Api\V1\Rider;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function updateLocation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $profile = $request->user()->riderProfile;
        $profile->update([
            'current_lat' => $data['lat'],
            'current_lng' => $data['lng'],
        ]);

        return response()->json(['message' => 'Location updated.']);
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
