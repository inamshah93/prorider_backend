<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Log;

class SmsNotificationService
{
    public function sendOrderUpdate(Order $order, string $message): void
    {
        if (! config('services.sms.enabled', false)) {
            Log::info('[SMS stub] '.$order->customer_phone.': '.$message);

            return;
        }

        // Wire Twilio or local SMS provider when credentials are configured.
        Log::info('[SMS] Would send to '.$order->customer_phone.': '.$message);
    }
}
