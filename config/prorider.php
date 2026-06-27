<?php

return [
    'platform_commission_rate' => (float) env('PLATFORM_COMMISSION_RATE', 0.10),
    'rider_commission_rate' => (float) env('RIDER_COMMISSION_RATE', 0.05),
    'shopify_webhook_secret' => env('SHOPIFY_WEBHOOK_SECRET'),
    'bank_webhook_secret' => env('BANK_WEBHOOK_SECRET'),
];
