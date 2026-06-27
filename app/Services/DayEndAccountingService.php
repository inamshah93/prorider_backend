<?php

namespace App\Services;

use App\Enums\LedgerEntryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\DayEndSnapshot;
use App\Models\FinancialLedger;
use App\Models\Order;
use App\Models\RiderProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DayEndAccountingService
{
    public function compile(?Carbon $date = null): DayEndSnapshot
    {
        $date = ($date ?? now()->subDay())->startOfDay();
        $end = $date->copy()->endOfDay();

        $deliveredOrders = Order::query()
            ->where('order_status', OrderStatus::Delivered)
            ->whereBetween('updated_at', [$date, $end])
            ->get();

        $totalCod = $deliveredOrders
            ->where('payment_method', PaymentMethod::Cod)
            ->sum('cod_amount');

        $totalRiderCash = RiderProfile::sum('cash_in_hand');

        $merchantPayables = FinancialLedger::query()
            ->where('entry_type', LedgerEntryType::MerchantPayable)
            ->whereBetween('created_at', [$date, $end])
            ->sum('amount');

        $platformFees = FinancialLedger::query()
            ->where('entry_type', LedgerEntryType::PlatformFee)
            ->whereBetween('created_at', [$date, $end])
            ->sum('amount');

        $riderCommissions = FinancialLedger::query()
            ->where('entry_type', LedgerEntryType::RiderCommission)
            ->whereBetween('created_at', [$date, $end])
            ->sum('amount');

        $netProfit = $platformFees - $riderCommissions;

        return DayEndSnapshot::updateOrCreate(
            ['snapshot_date' => $date->toDateString()],
            [
                'total_cod_collected' => $totalCod,
                'total_rider_cash' => $totalRiderCash,
                'total_merchant_payables' => $merchantPayables,
                'platform_net_profit' => $netProfit,
                'orders_delivered' => $deliveredOrders->count(),
                'metadata' => [
                    'compiled_at' => now()->toIso8601String(),
                ],
            ],
        );
    }

    public function recordDeliveryLedger(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $codAmount = (float) $order->cod_amount;
            $riderRate = (float) config('prorider.rider_commission_rate', 0.05);
            $platformRate = (float) config('prorider.platform_commission_rate', 0.10);

            if ($order->payment_method === PaymentMethod::Cod && $codAmount > 0) {
                FinancialLedger::create([
                    'order_id' => $order->id,
                    'merchant_id' => $order->merchant_id,
                    'rider_id' => $order->rider_id,
                    'entry_type' => LedgerEntryType::CodCollected,
                    'amount' => $codAmount,
                    'reference' => $order->order_reference_number,
                ]);

                if ($order->rider_id) {
                    $commission = round($codAmount * $riderRate, 2);
                    FinancialLedger::create([
                        'order_id' => $order->id,
                        'rider_id' => $order->rider_id,
                        'entry_type' => LedgerEntryType::RiderCommission,
                        'amount' => $commission,
                        'reference' => $order->order_reference_number,
                    ]);

                    RiderProfile::where('user_id', $order->rider_id)
                        ->increment('cash_in_hand', $codAmount - $commission);
                }

                $platformFee = round($codAmount * $platformRate, 2);
                $merchantPayable = $codAmount - $platformFee - ($commission ?? 0);

                FinancialLedger::create([
                    'order_id' => $order->id,
                    'merchant_id' => $order->merchant_id,
                    'entry_type' => LedgerEntryType::PlatformFee,
                    'amount' => $platformFee,
                    'reference' => $order->order_reference_number,
                ]);

                FinancialLedger::create([
                    'order_id' => $order->id,
                    'merchant_id' => $order->merchant_id,
                    'entry_type' => LedgerEntryType::MerchantPayable,
                    'amount' => max(0, $merchantPayable),
                    'reference' => $order->order_reference_number,
                ]);
            }
        });
    }
}
