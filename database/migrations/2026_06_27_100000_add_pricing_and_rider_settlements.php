<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('default_delivery_charge', 12, 2)->default(400);
            $table->decimal('default_rider_commission_rate', 5, 4)->default(0.05);
            $table->timestamps();
        });

        DB::table('platform_settings')->insert([
            'default_delivery_charge' => 400,
            'default_rider_commission_rate' => 0.05,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('merchants', function (Blueprint $table) {
            $table->decimal('delivery_charge', 12, 2)->nullable()->after('store_name');
        });

        Schema::table('rider_profiles', function (Blueprint $table) {
            $table->decimal('commission_rate', 5, 4)->nullable()->after('cash_in_hand');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('delivery_charge', 12, 2)->default(400)->after('cod_amount');
            $table->decimal('rider_commission_amount', 12, 2)->nullable()->after('delivery_charge');
        });

        Schema::create('rider_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('cash_before', 12, 2);
            $table->decimal('cash_after', 12, 2);
            $table->string('proof_image_path');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_settlements');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_charge', 'rider_commission_amount']);
        });
        Schema::table('rider_profiles', function (Blueprint $table) {
            $table->dropColumn('commission_rate');
        });
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn('delivery_charge');
        });
        Schema::dropIfExists('platform_settings');
    }
};
