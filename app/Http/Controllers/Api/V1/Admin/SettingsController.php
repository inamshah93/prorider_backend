<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function pricing(): JsonResponse
    {
        $settings = PlatformSetting::current();

        return response()->json([
            'data' => [
                'default_delivery_charge' => $settings->default_delivery_charge,
                'default_rider_commission_rate' => $settings->default_rider_commission_rate,
                'default_rider_commission_percent' => round((float) $settings->default_rider_commission_rate * 100, 2),
            ],
        ]);
    }

    public function updatePricing(Request $request): JsonResponse
    {
        $data = $request->validate([
            'default_delivery_charge' => 'required|numeric|min:0',
            'default_rider_commission_rate' => 'nullable|numeric|min:0|max:1',
            'default_rider_commission_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        $rate = $data['default_rider_commission_rate']
            ?? (($data['default_rider_commission_percent'] ?? 5) / 100);

        $settings = PlatformSetting::current();
        $settings->update([
            'default_delivery_charge' => $data['default_delivery_charge'],
            'default_rider_commission_rate' => $rate,
        ]);

        return response()->json([
            'data' => [
                'default_delivery_charge' => $settings->default_delivery_charge,
                'default_rider_commission_rate' => $settings->default_rider_commission_rate,
                'default_rider_commission_percent' => round((float) $settings->default_rider_commission_rate * 100, 2),
            ],
        ]);
    }
}
