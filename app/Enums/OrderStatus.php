<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Created = 'created';
    case ReadyToShip = 'ready_to_ship';
    case Dispatched = 'dispatched';
    case PickedUp = 'picked_up';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
    case Returned = 'returned';
}
