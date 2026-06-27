<?php

namespace App\Services;

use App\Enums\MerchantPrepStatus;
use App\Enums\OrderStatus;
use App\Events\OrderReadyToShip;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\User;

class OrderStateMachine
{
    /** @var array<string, list<string>> */
    private array $transitions = [
        'created' => ['ready_to_ship', 'cancelled'],
        'ready_to_ship' => ['dispatched', 'cancelled'],
        'dispatched' => ['picked_up'],
        'picked_up' => ['delivered'],
    ];

    public function transition(Order $order, OrderStatus $to, ?User $actor = null, array $metadata = []): Order
    {
        $from = $order->order_status;

        if (! $this->canTransition($from, $to)) {
            throw new InvalidOrderTransitionException(
                "Cannot transition order from {$from->value} to {$to->value}."
            );
        }

        $order->order_status = $to;
        $order->save();

        OrderEvent::create([
            'order_id' => $order->id,
            'actor_id' => $actor?->id,
            'event_type' => 'order_status_changed',
            'from_status' => $from->value,
            'to_status' => $to->value,
            'metadata' => $metadata,
        ]);

        if ($to === OrderStatus::ReadyToShip) {
            event(new OrderReadyToShip($order));
        }

        return $order->fresh();
    }

    public function advancePrep(Order $order, MerchantPrepStatus $to, ?User $actor = null): Order
    {
        $valid = match ($order->merchant_prep_status) {
            MerchantPrepStatus::Created => $to === MerchantPrepStatus::LabelGenerated,
            MerchantPrepStatus::LabelGenerated => $to === MerchantPrepStatus::Packed,
            default => false,
        };

        if (! $valid) {
            throw new InvalidOrderTransitionException(
                "Cannot advance prep status from {$order->merchant_prep_status->value} to {$to->value}."
            );
        }

        $from = $order->merchant_prep_status;
        $order->merchant_prep_status = $to;

        if ($to === MerchantPrepStatus::LabelGenerated && ! $order->awb_number) {
            $order->awb_number = 'AWB-'.strtoupper(substr(md5((string) $order->id), 0, 8));
        }

        $order->save();

        OrderEvent::create([
            'order_id' => $order->id,
            'actor_id' => $actor?->id,
            'event_type' => 'merchant_prep_changed',
            'from_status' => $from->value,
            'to_status' => $to->value,
        ]);

        return $order->fresh();
    }

    public function canTransition(OrderStatus $from, OrderStatus $to): bool
    {
        return in_array($to->value, $this->transitions[$from->value] ?? [], true);
    }
}
