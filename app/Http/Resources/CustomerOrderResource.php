<?php

namespace App\Http\Resources;

use App\Services\CustomerDeliveryLocationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locationService = app(CustomerDeliveryLocationService::class);

        return [
            'id' => $this->id,
            'order_reference_number' => $this->order_reference_number,
            'order_status' => $this->order_status?->value ?? $this->order_status,
            'payment_status' => $this->payment_status?->value ?? $this->payment_status,
            'payment_method' => $this->payment_method?->value ?? $this->payment_method,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'delivery_address' => $this->delivery_address,
            'delivery_lat' => $this->delivery_lat !== null ? (float) $this->delivery_lat : null,
            'delivery_lng' => $this->delivery_lng !== null ? (float) $this->delivery_lng : null,
            'can_update_delivery_location' => $locationService->canUpdateLocation($this->resource),
            'target_city' => $this->whenLoaded('targetCity', fn () => $this->targetCity?->name),
            'cod_amount' => $this->cod_amount,
            'delivery_charge' => $this->delivery_charge,
            'item_details' => $this->item_details,
            'awb_number' => $this->awb_number,
            'merchant' => $this->whenLoaded('merchant', fn () => [
                'id' => $this->merchant?->id,
                'store_name' => $this->merchant?->store_name,
            ]),
            'rider' => $this->whenLoaded('rider', fn () => $this->rider ? [
                'name' => $this->rider->name,
                'phone' => $this->rider->phone,
            ] : null),
            'events' => $this->whenLoaded('events'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
