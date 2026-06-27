<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Order;

class PaymentWebhookService
{
    public function handleBankSuccess(array $payload): Order
    {
        $orderRef = $payload['order_reference'] ?? $payload['reference'] ?? null;

        if (! $orderRef) {
            throw new \InvalidArgumentException('Order reference is required.');
        }

        $order = Order::where('order_reference_number', $orderRef)->firstOrFail();
        $order->update(['payment_status' => PaymentStatus::Paid]);

        $order->events()->create([
            'event_type' => 'payment_confirmed',
            'metadata' => ['source' => 'bank_webhook', 'payload' => $payload],
        ]);

        return $order->fresh();
    }
}
