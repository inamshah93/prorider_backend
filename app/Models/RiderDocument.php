<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderDocument extends Model
{
    protected $fillable = [
        'rider_profile_id',
        'document_type',
        'file_path',
        'status',
        'rejection_reason',
    ];

    public function riderProfile(): BelongsTo
    {
        return $this->belongsTo(RiderProfile::class);
    }
}
