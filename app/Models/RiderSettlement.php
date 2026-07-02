<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class RiderSettlement extends Model
{
    protected $fillable = [
        'rider_id',
        'admin_id',
        'amount',
        'cash_before',
        'cash_after',
        'proof_image_path',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'cash_before' => 'decimal:2',
            'cash_after' => 'decimal:2',
        ];
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function getProofUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->proof_image_path);
    }
}
