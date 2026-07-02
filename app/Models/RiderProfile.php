<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderProfile extends Model
{
    protected $fillable = [
        'user_id',
        'is_online',
        'current_lat',
        'current_lng',
        'cash_in_hand',
        'commission_rate',
        'documents_verified',
        'assigned_city_id',
    ];

    protected function casts(): array
    {
        return [
            'is_online' => 'boolean',
            'documents_verified' => 'boolean',
            'cash_in_hand' => 'decimal:2',
            'commission_rate' => 'decimal:4',
            'current_lat' => 'decimal:7',
            'current_lng' => 'decimal:7',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function effectiveCommissionRate(): float
    {
        if ($this->commission_rate !== null) {
            return (float) $this->commission_rate;
        }

        return PlatformSetting::defaultRiderCommissionRate();
    }

    public function assignedCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'assigned_city_id');
    }
}
