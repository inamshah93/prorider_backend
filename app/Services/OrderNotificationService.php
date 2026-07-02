<?php

namespace App\Services;

use App\Jobs\SendFcmNotificationJob;
use App\Models\Order;
use App\Models\User;

class OrderNotificationService
{
    public function notifyRiderAssigned(Order $order): void
    {
        $rider = $order->rider;
        if (! $rider?->device_token) {
            return;
        }

        SendFcmNotificationJob::dispatch(
            $rider->device_token,
            'Order assigned',
            "You have been assigned order {$order->order_reference_number}.",
            ['order_id' => (string) $order->id, 'type' => 'order_assigned'],
        );
    }

    public function notifyCustomerStatus(Order $order, string $title, string $body): void
    {
        $customer = $order->customerUser;
        if (! $customer?->device_token) {
            return;
        }

        SendFcmNotificationJob::dispatch(
            $customer->device_token,
            $title,
            $body,
            ['order_id' => (string) $order->id, 'type' => 'order_status'],
        );
    }

    public function notifyMerchant(Order $order, string $title, string $body): void
    {
        $merchantUser = $order->merchant?->user;
        if (! $merchantUser?->device_token) {
            return;
        }

        SendFcmNotificationJob::dispatch(
            $merchantUser->device_token,
            $title,
            $body,
            ['order_id' => (string) $order->id, 'type' => 'order_status'],
        );
    }
}
