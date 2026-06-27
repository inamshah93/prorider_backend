<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    protected $fillable = ['name', 'province', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
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
