<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE financial_ledgers MODIFY entry_type ENUM(
            'cod_collected',
            'rider_commission',
            'platform_fee',
            'merchant_payable',
            'manual_override',
            'rider_settlement'
        ) NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE financial_ledgers MODIFY entry_type ENUM(
            'cod_collected',
            'rider_commission',
            'platform_fee',
            'merchant_payable',
            'manual_override'
        ) NOT NULL");
    }
};
