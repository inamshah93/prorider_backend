<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('merchant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('entry_type', ['cod_collected', 'rider_commission', 'platform_fee', 'merchant_payable', 'manual_override']);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2)->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payment_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->enum('previous_status', ['pending', 'paid', 'failed']);
            $table->enum('new_status', ['pending', 'paid', 'failed']);
            $table->text('reason');
            $table->foreignId('ledger_id')->nullable()->constrained('financial_ledgers')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('day_end_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date')->unique();
            $table->decimal('total_cod_collected', 14, 2)->default(0);
            $table->decimal('total_rider_cash', 14, 2)->default(0);
            $table->decimal('total_merchant_payables', 14, 2)->default(0);
            $table->decimal('platform_net_profit', 14, 2)->default(0);
            $table->unsignedInteger('orders_delivered')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('day_end_snapshots');
        Schema::dropIfExists('payment_overrides');
        Schema::dropIfExists('financial_ledgers');
    }
};
