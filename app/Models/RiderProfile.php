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
        'documents_verified',
        'assigned_city_id',
    ];

    protected function casts(): array
    {
        return [
            'is_online' => 'boolean',
            'documents_verified' => 'boolean',
            'cash_in_hand' => 'decimal:2',
            'current_lat' => 'decimal:7',
            'current_lng' => 'decimal:7',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'assigned_city_id');
    }
}
