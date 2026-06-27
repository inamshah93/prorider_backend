<?php

namespace App\Listeners;

use App\Events\OrderDelivered;
use App\Events\OrderReadyToShip;
use App\Jobs\DispatchNearestRidersJob;
use App\Services\DayEndAccountingService;

class OrderEventSubscriber
{
    public function handleOrderReadyToShip(OrderReadyToShip $event): void
    {
        DispatchNearestRidersJob::dispatch($event->order);
    }

    public function handleOrderDelivered(OrderDelivered $event): void
    {
        app(DayEndAccountingService::class)->recordDeliveryLedger($event->order);
    }

    public function subscribe($events): array
    {
        return [
            OrderReadyToShip::class => 'handleOrderReadyToShip',
            OrderDelivered::class => 'handleOrderDelivered',
        ];
    }
}
