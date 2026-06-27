<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'province' => $this->province,
            'is_active' => $this->is_active,
            'aliases' => $this->whenLoaded('aliases', fn () => $this->aliases->pluck('alias_name')),
            'riders_count' => $this->when(isset($this->riders_count), $this->riders_count),
        ];
    }
}
