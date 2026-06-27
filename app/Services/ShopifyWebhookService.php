<?php

namespace App\Services;

use App\Enums\MerchantPrepStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Merchant;
use App\Models\Order;
use Illuminate\Support\Str;

class ShopifyWebhookService
{
    public function __construct(
        private CityResolverService $cityResolver,
    ) {}

    public function handleOrderCreate(array $payload, Merchant $merchant): Order
    {
        $shipping = $payload['shipping_address'] ?? [];
        $rawCity = $shipping['city'] ?? $payload['destination']['city'] ?? '';
        $city = $this->cityResolver->resolve($rawCity);

        $lineItems = collect($payload['line_items'] ?? [])->map(fn ($item) => [
            'name' => $item['title'] ?? 'Product',
            'sku' => $item['sku'] ?? Str::slug($item['title'] ?? 'item'),
            'quantity' => $item['quantity'] ?? 1,
            'price' => $item['price'] ?? 0,
        ])->all();

        $order = Order::create([
            'order_reference_number' => 'SH-'.($payload['order_number'] ?? Str::random(8)),
            'merchant_id' => $merchant->id,
            'customer_name' => trim(($shipping['first_name'] ?? '').' '.($shipping['last_name'] ?? '')) ?: 'Customer',
            'customer_phone' => $shipping['phone'] ?? $payload['phone'] ?? '',
            'delivery_address' => $this->formatAddress($shipping),
            'target_city_id' => $city->id,
            'parcel_weight' => $payload['total_weight'] ?? 0,
            'item_details' => $lineItems,
            'cod_amount' => (float) ($payload['total_price'] ?? 0),
            'payment_method' => PaymentMethod::Cod,
            'payment_status' => PaymentStatus::Pending,
            'order_status' => OrderStatus::Created,
            'merchant_prep_status' => MerchantPrepStatus::Created,
            'shopify_order_id' => (string) ($payload['id'] ?? ''),
        ]);

        $order->events()->create([
            'event_type' => 'order_created',
            'to_status' => OrderStatus::Created->value,
            'metadata' => ['source' => 'shopify'],
        ]);

        return $order;
    }

    private function formatAddress(array $shipping): string
    {
        return collect([
            $shipping['address1'] ?? null,
            $shipping['address2'] ?? null,
            $shipping['city'] ?? null,
        ])->filter()->implode(', ');
    }
}
