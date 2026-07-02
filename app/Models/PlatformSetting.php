<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = [
        'default_delivery_charge',
        'default_rider_commission_rate',
    ];

    protected function casts(): array
    {
        return [
            'default_delivery_charge' => 'decimal:2',
            'default_rider_commission_rate' => 'decimal:4',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'default_delivery_charge' => config('prorider.default_delivery_charge', 400),
            'default_rider_commission_rate' => config('prorider.rider_commission_rate', 0.05),
        ]);
    }

    public static function defaultDeliveryCharge(): float
    {
        return (float) static::current()->default_delivery_charge;
    }

    public static function defaultRiderCommissionRate(): float
    {
        return (float) static::current()->default_rider_commission_rate;
    }
}
