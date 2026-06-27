<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    case CodCollected = 'cod_collected';
    case RiderCommission = 'rider_commission';
    case PlatformFee = 'platform_fee';
    case MerchantPayable = 'merchant_payable';
    case ManualOverride = 'manual_override';
}
