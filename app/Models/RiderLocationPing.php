<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderLocationPing extends Model
{
    protected $fillable = [
        'rider_profile_id',
        'lat',
        'lng',
        'accuracy_m',
        'speed_mps',
        'heading_deg',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'accuracy_m' => 'decimal:2',
            'speed_mps' => 'decimal:2',
            'heading_deg' => 'decimal:2',
        ];
    }

    public function riderProfile(): BelongsTo
    {
        return $this->belongsTo(RiderProfile::class);
    }
}

