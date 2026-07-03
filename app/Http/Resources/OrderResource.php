<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_reference_number' => $this->order_reference_number,
            'merchant_id' => $this->merchant_id,
            'rider_id' => $this->rider_id,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'customer_user' => $this->whenLoaded('customerUser', fn () => $this->customerUser ? [
                'id' => $this->customerUser->id,
                'name' => $this->customerUser->name,
                'email' => $this->customerUser->email,
                'phone' => $this->customerUser->phone,
            ] : null),
            'delivery_address' => $this->delivery_address,
            'target_city_id' => $this->target_city_id,
            'target_city' => $this->whenLoaded('targetCity', fn () => $this->targetCity?->name),
            'parcel_weight' => $this->parcel_weight,
            'item_details' => $this->item_details,
            'cod_amount' => $this->cod_amount,
            'delivery_charge' => $this->delivery_charge,
            'rider_commission_amount' => $this->rider_commission_amount,
            'payment_method' => $this->payment_method?->value ?? $this->payment_method,
            'payment_status' => $this->payment_status?->value ?? $this->payment_status,
            'order_status' => $this->order_status?->value ?? $this->order_status,
            'merchant_prep_status' => $this->merchant_prep_status?->value ?? $this->merchant_prep_status,
            'awb_number' => $this->awb_number,
            'assignment_status' => $this->assignment_status,
            'pod_photo_path' => $this->pod_photo_path ? url('storage/'.$this->pod_photo_path) : null,
            'signature_path' => $this->signature_path ? url('storage/'.$this->signature_path) : null,
            'failure_reason' => $this->failure_reason,
            'failed_at' => $this->failed_at,
            'merchant' => $this->whenLoaded('merchant', fn () => new MerchantResource($this->merchant)),
            'rider' => $this->whenLoaded('rider', fn () => $this->rider ? [
                'id' => $this->rider->id,
                'name' => $this->rider->name,
                'phone' => $this->rider->phone,
            ] : null),
            'events' => $this->whenLoaded('events'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
