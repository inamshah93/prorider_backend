<?php

namespace App\Services;

use App\Models\RiderLocationPing;
use App\Models\RiderProfile;
use Carbon\CarbonImmutable;

class RiderRouteReportService
{
    /**
     * @return array{
     *   total_distance_km: float,
     *   ping_count: int,
     *   active_duration_minutes: int,
     *   stops: array<int, array{lat: float, lng: float, arrived_at: string, left_at: string, duration_minutes: int}>,
     *   timeline: array<int, array{recorded_at: string, lat: float, lng: float, speed_mps: ?float, accuracy_m: ?float}>
     * }
     */
    public function build(RiderProfile $rider, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $pings = RiderLocationPing::query()
            ->where('rider_profile_id', $rider->id)
            ->whereBetween('recorded_at', [$from, $to])
            ->orderBy('recorded_at')
            ->get();

        $timeline = $pings->map(fn (RiderLocationPing $p) => [
            'recorded_at' => $p->recorded_at?->toISOString() ?? '',
            'lat' => (float) $p->lat,
            'lng' => (float) $p->lng,
            'speed_mps' => $p->speed_mps !== null ? (float) $p->speed_mps : null,
            'accuracy_m' => $p->accuracy_m !== null ? (float) $p->accuracy_m : null,
        ])->values()->all();

        $totalMeters = 0.0;
        $activeSeconds = 0;

        for ($i = 1; $i < $pings->count(); $i++) {
            $a = $pings[$i - 1];
            $b = $pings[$i];

            // Always include distance between consecutive pings; gaps only affect "active duration".
            $dist = $this->distanceMeters((float) $a->lat, (float) $a->lng, (float) $b->lat, (float) $b->lng);
            $totalMeters += $dist;

            $dt = $b->recorded_at?->diffInSeconds($a->recorded_at) ?? 0;
            if ($dt <= 0) continue;

            // Ignore huge gaps (app killed/offline) from active duration.
            if ($dt > 10 * 60) continue;

            $activeSeconds += $dt;
        }

        // Very simple stop detection: if rider stays within 50m for >=5 minutes.
        $stops = [];
        $stopStart = null;
        $stopAnchor = null;

        foreach ($pings as $idx => $ping) {
            if ($stopStart === null) {
                $stopStart = $ping;
                $stopAnchor = $ping;
                continue;
            }

            $dist = $this->distanceMeters((float) $stopAnchor->lat, (float) $stopAnchor->lng, (float) $ping->lat, (float) $ping->lng);
            if ($dist <= 50) {
                continue;
            }

            // moved away: close stop window
            $duration = $ping->recorded_at?->diffInSeconds($stopStart->recorded_at) ?? 0;
            if ($duration >= 5 * 60) {
                $stops[] = [
                    'lat' => (float) $stopStart->lat,
                    'lng' => (float) $stopStart->lng,
                    'arrived_at' => $stopStart->recorded_at?->toISOString() ?? '',
                    'left_at' => $ping->recorded_at?->toISOString() ?? '',
                    'duration_minutes' => (int) round($duration / 60),
                ];
            }

            $stopStart = $ping;
            $stopAnchor = $ping;
        }

        return [
            'total_distance_km' => round($totalMeters / 1000, 3),
            'ping_count' => $pings->count(),
            'active_duration_minutes' => (int) round($activeSeconds / 60),
            'stops' => $stops,
            'timeline' => $timeline,
        ];
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
}

