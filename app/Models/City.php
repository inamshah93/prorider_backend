<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    protected $fillable = ['name', 'province', 'is_active', 'delivery_surcharge', 'weight_rate_per_kg'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'delivery_surcharge' => 'decimal:2', 'weight_rate_per_kg' => 'decimal:2'];
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(CityAlias::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'target_city_id');
    }
}
