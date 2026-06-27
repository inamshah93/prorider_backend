<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\ProximityDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchNearestRidersJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function handle(ProximityDispatchService $dispatchService): void
    {
        $dispatchService->dispatchToNearestRiders($this->order);
    }
}
