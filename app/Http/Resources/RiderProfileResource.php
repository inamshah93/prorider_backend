<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RiderProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'is_online' => $this->is_online,
            'current_lat' => $this->current_lat,
            'current_lng' => $this->current_lng,
            'cash_in_hand' => $this->cash_in_hand,
            'documents_verified' => $this->documents_verified,
            'assigned_city_id' => $this->assigned_city_id,
            'assigned_city' => $this->whenLoaded('assignedCity', fn () => $this->assignedCity?->name),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'phone' => $this->user->phone,
            ]),
        ];
    }
}
