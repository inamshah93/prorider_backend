<?php

namespace App\Services;

class EtaCalculator
{
    /** Average city speed in km/h for rough ETA. */
    private const AVG_SPEED_KMH = 25;

    public static function minutesBetween(?float $fromLat, ?float $fromLng, ?float $toLat, ?float $toLng): ?int
    {
        if ($fromLat === null || $fromLng === null || $toLat === null || $toLng === null) {
            return null;
        }

        if ($toLat == 0.0 && $toLng == 0.0) {
            return null;
        }

        $km = self::haversineKm($fromLat, $fromLng, $toLat, $toLng);

        if ($km <= 0) {
            return 1;
        }

        return max(1, (int) round(($km / self::AVG_SPEED_KMH) * 60));
    }

    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
