<?php

namespace App\Services;

use App\Models\City;
use App\Models\Merchant;

class DeliveryPricingService
{
    public function calculate(Merchant $merchant, City $city, float $parcelWeightKg = 0): float
    {
        $base = $merchant->effectiveDeliveryCharge();
        $surcharge = (float) ($city->delivery_surcharge ?? 0);
        $weightRate = (float) ($city->weight_rate_per_kg ?? 0);
        $weightFee = max(0, $parcelWeightKg) * $weightRate;

        return round($base + $surcharge + $weightFee, 2);
    }
}
