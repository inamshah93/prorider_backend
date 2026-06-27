<?php

namespace App\Models;

use App\Enums\MerchantPrepStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_reference_number',
        'merchant_id',
        'rider_id',
        'customer_name',
        'customer_phone',
        'delivery_address',
        'target_city_id',
        'parcel_weight',
        'item_details',
        'cod_amount',
        'payment_method',
        'payment_status',
        'order_status',
        'merchant_prep_status',
        'awb_number',
        'shopify_order_id',
        'pickup_lat',
        'pickup_lng',
    ];

    protected function casts(): array
    {
        return [
            'item_details' => 'array',
            'parcel_weight' => 'decimal:2',
            'cod_amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'order_status' => OrderStatus::class,
            'merchant_prep_status' => MerchantPrepStatus::class,
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function targetCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'target_city_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class);
    }

    public function ledgers(): HasMany
    {
        return $this->hasMany(FinancialLedger::class);
    }
}
