<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status?->value ?? $this->status,
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'merchant' => $this->whenLoaded('merchant', fn () => new MerchantResource($this->merchant)),
            'rider_profile' => $this->whenLoaded('riderProfile', fn () => new RiderProfileResource($this->riderProfile)),
        ];
    }
}
