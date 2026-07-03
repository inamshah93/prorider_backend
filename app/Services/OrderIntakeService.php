<?php

namespace App\Services;

use App\Enums\MerchantPrepStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Str;

class OrderIntakeService
{
    public function __construct(
        private CityResolverService $cityResolver,
        private DeliveryPricingService $pricing,
    ) {}

    public function createManualOrder(Merchant $merchant, User $actor, array $data): Order
    {
        $city = $this->cityResolver->resolve($data['city_name'] ?? $data['target_city'] ?? '');

        $items = $data['items'] ?? [];
        $catalog = $merchant->manual_saved_items ?? [];
        $mergedCatalog = $this->mergeCatalogItems($catalog, $items);
        $merchant->update(['manual_saved_items' => $mergedCatalog]);

        $customerPhone = PhoneNormalizer::normalize($data['customer_phone']) ?? $data['customer_phone'];
        $customerUserId = User::role('Customer')->where('phone', $customerPhone)->value('id');
        $parcelWeight = (float) ($data['parcel_weight'] ?? 0);

        $order = Order::create([
            'order_reference_number' => $this->generateReference(),
            'merchant_id' => $merchant->id,
            'customer_user_id' => $customerUserId,
            'customer_name' => $data['customer_name'],
            'customer_phone' => $customerPhone,
            'delivery_address' => $data['delivery_address'],
            'target_city_id' => $city->id,
            'parcel_weight' => $parcelWeight,
            'item_details' => $items,
            'cod_amount' => $data['cod_amount'] ?? 0,
            'delivery_charge' => $this->pricing->calculate($merchant, $city, $parcelWeight),
            'payment_method' => $data['payment_method'] ?? PaymentMethod::Cod->value,
            'payment_status' => PaymentStatus::Pending,
            'order_status' => OrderStatus::Created,
            'merchant_prep_status' => MerchantPrepStatus::Created,
        ]);

        $order->events()->create([
            'actor_id' => $actor->id,
            'event_type' => 'order_created',
            'to_status' => OrderStatus::Created->value,
            'metadata' => ['source' => 'manual'],
        ]);

        return $order->load(['targetCity', 'merchant']);
    }

    private function generateReference(): string
    {
        do {
            $ref = 'PR-'.strtoupper(Str::random(8));
        } while (Order::where('order_reference_number', $ref)->exists());

        return $ref;
    }

    private function mergeCatalogItems(array $catalog, array $items): array
    {
        $indexed = collect($catalog)->keyBy('sku');

        foreach ($items as $item) {
            $sku = $item['sku'] ?? Str::slug($item['name'] ?? 'item');
            $indexed[$sku] = [
                'sku' => $sku,
                'name' => $item['name'] ?? 'Item',
                'weight' => $item['weight'] ?? 0,
                'price' => $item['price'] ?? 0,
            ];
        }

        return $indexed->values()->all();
    }
}
