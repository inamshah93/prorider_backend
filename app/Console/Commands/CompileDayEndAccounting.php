<?php

namespace App\Console\Commands;

use App\Services\DayEndAccountingService;
use Illuminate\Console\Command;

class CompileDayEndAccounting extends Command
{
    protected $signature = 'prorider:compile-day-end {--date=}';

    protected $description = 'Compile midnight financial metrics and settlements';

    public function handle(DayEndAccountingService $service): int
    {
        $date = $this->option('date')
            ? \Carbon\Carbon::parse($this->option('date'))
            : null;

        $snapshot = $service->compile($date);

        $this->info("Day-end snapshot compiled for {$snapshot->snapshot_date->toDateString()}");

        return self::SUCCESS;
    }
}
