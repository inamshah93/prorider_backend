<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CityAlias extends Model
{
    protected $fillable = ['city_id', 'alias_name'];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
