<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Merchant extends Model
{
    protected $fillable = [
        'user_id',
        'store_name',
        'delivery_charge',
        'shopify_shop_url',
        'shopify_access_token',
        'manual_saved_items',
    ];

    protected function casts(): array
    {
        return [
            'manual_saved_items' => 'array',
            'shopify_access_token' => 'encrypted',
            'delivery_charge' => 'decimal:2',
        ];
    }

    public function effectiveDeliveryCharge(): float
    {
        if ($this->delivery_charge !== null) {
            return (float) $this->delivery_charge;
        }

        return PlatformSetting::defaultDeliveryCharge();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function ledgers(): HasMany
    {
        return $this->hasMany(FinancialLedger::class);
    }
}
