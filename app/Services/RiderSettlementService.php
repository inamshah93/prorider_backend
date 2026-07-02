<?php

namespace App\Services;

use App\Enums\LedgerEntryType;
use App\Models\FinancialLedger;
use App\Models\RiderProfile;
use App\Models\RiderSettlement;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RiderSettlementService
{
    public function record(User $rider, User $admin, float $amount, UploadedFile $proof, ?string $notes = null): RiderSettlement
    {
        return DB::transaction(function () use ($rider, $admin, $amount, $proof, $notes) {
            $profile = RiderProfile::where('user_id', $rider->id)->lockForUpdate()->firstOrFail();
            $cashBefore = (float) $profile->cash_in_hand;

            abort_if($amount <= 0, 422, 'Settlement amount must be greater than zero.');
            abort_if($amount > $cashBefore, 422, 'Settlement amount exceeds rider cash in hand.');

            $path = $proof->store('rider-settlements', 'public');
            $cashAfter = round($cashBefore - $amount, 2);

            $settlement = RiderSettlement::create([
                'rider_id' => $rider->id,
                'admin_id' => $admin->id,
                'amount' => $amount,
                'cash_before' => $cashBefore,
                'cash_after' => $cashAfter,
                'proof_image_path' => $path,
                'notes' => $notes,
            ]);

            $profile->update(['cash_in_hand' => $cashAfter]);

            FinancialLedger::create([
                'rider_id' => $rider->id,
                'entry_type' => LedgerEntryType::RiderSettlement,
                'amount' => $amount,
                'reference' => 'SETTLE-'.$settlement->id,
                'notes' => $notes,
                'created_by' => $admin->id,
            ]);

            return $settlement;
        });
    }

    public function walletSummary(User $rider): array
    {
        $profile = $rider->riderProfile;
        $riderId = $rider->id;

        $totalCollected = (float) FinancialLedger::query()
            ->where('rider_id', $riderId)
            ->where('entry_type', LedgerEntryType::CodCollected)
            ->sum('amount');

        $totalCommission = (float) FinancialLedger::query()
            ->where('rider_id', $riderId)
            ->where('entry_type', LedgerEntryType::RiderCommission)
            ->sum('amount');

        $totalSettled = (float) RiderSettlement::query()
            ->where('rider_id', $riderId)
            ->sum('amount');

        return [
            'cash_in_hand' => (float) ($profile->cash_in_hand ?? 0),
            'remaining_to_pay' => (float) ($profile->cash_in_hand ?? 0),
            'total_collected' => $totalCollected,
            'total_commission_earned' => $totalCommission,
            'total_settled' => $totalSettled,
            'commission_rate' => $profile?->effectiveCommissionRate(),
            'is_online' => (bool) ($profile->is_online ?? false),
        ];
    }
}
