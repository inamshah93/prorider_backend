<?php

namespace App\Console\Commands;

use App\Models\RiderLocationPing;
use Illuminate\Console\Command;

class CleanupRiderLocationPings extends Command
{
    protected $signature = 'prorider:cleanup-rider-location-pings {--days=}';

    protected $description = 'Delete rider location history older than N days';

    public function handle(): int
    {
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) config('prorider.rider_location_retention_days', 60);

        if ($days <= 0) {
            $this->warn('Retention days must be > 0. Skipping cleanup.');
            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);
        $deleted = RiderLocationPing::query()->where('recorded_at', '<', $cutoff)->delete();

        $this->info("Deleted {$deleted} rider location pings older than {$days} days.");

        return self::SUCCESS;
    }
}

