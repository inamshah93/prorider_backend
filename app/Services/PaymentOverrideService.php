<?php

namespace App\Services;

use App\Enums\LedgerEntryType;
use App\Enums\PaymentStatus;
use App\Models\FinancialLedger;
use App\Models\Order;
use App\Models\PaymentOverride;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

class PaymentOverrideService
{
    public function override(Order $order, User $admin, PaymentStatus $newStatus, string $reason): PaymentOverride
    {
        $previous = $order->payment_status;
        $order->update(['payment_status' => $newStatus]);

        $ledger = FinancialLedger::create([
            'order_id' => $order->id,
            'merchant_id' => $order->merchant_id,
            'rider_id' => $order->rider_id,
            'entry_type' => LedgerEntryType::ManualOverride,
            'amount' => $order->cod_amount,
            'reference' => $order->order_reference_number,
            'notes' => $reason,
            'created_by' => $admin->id,
        ]);

        $override = PaymentOverride::create([
            'order_id' => $order->id,
            'admin_id' => $admin->id,
            'previous_status' => $previous,
            'new_status' => $newStatus,
            'reason' => $reason,
            'ledger_id' => $ledger->id,
        ]);

        activity()
            ->causedBy($admin)
            ->performedOn($order)
            ->withProperties([
                'previous_status' => $previous->value,
                'new_status' => $newStatus->value,
                'reason' => $reason,
            ])
            ->log('payment_manual_override');

        return $override->load('ledger');
    }
}
