<?php

return [
    'default_delivery_charge' => (float) env('DEFAULT_DELIVERY_CHARGE', 400),
    'platform_commission_rate' => (float) env('PLATFORM_COMMISSION_RATE', 0.10),
    'rider_commission_rate' => (float) env('RIDER_COMMISSION_RATE', 0.05),
    'rider_location_retention_days' => (int) env('RIDER_LOCATION_RETENTION_DAYS', 60),
    'shopify_webhook_secret' => env('SHOPIFY_WEBHOOK_SECRET'),
    'bank_webhook_secret' => env('BANK_WEBHOOK_SECRET'),
];
