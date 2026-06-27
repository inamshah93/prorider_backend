<?php

namespace App\Http\Controllers\Api\V1\Rider;

use App\Http\Controllers\Controller;
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
}
