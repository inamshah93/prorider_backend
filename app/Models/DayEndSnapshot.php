<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DayEndSnapshot extends Model
{
    protected $fillable = [
        'snapshot_date',
        'total_cod_collected',
        'total_rider_cash',
        'total_merchant_payables',
        'platform_net_profit',
        'orders_delivered',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'total_cod_collected' => 'decimal:2',
            'total_rider_cash' => 'decimal:2',
            'total_merchant_payables' => 'decimal:2',
            'platform_net_profit' => 'decimal:2',
            'metadata' => 'array',
        ];
    }
}
