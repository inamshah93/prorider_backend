<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentOverride extends Model
{
    protected $fillable = [
        'order_id',
        'admin_id',
        'previous_status',
        'new_status',
        'reason',
        'ledger_id',
    ];

    protected function casts(): array
    {
        return [
            'previous_status' => PaymentStatus::class,
            'new_status' => PaymentStatus::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(FinancialLedger::class, 'ledger_id');
    }
}
