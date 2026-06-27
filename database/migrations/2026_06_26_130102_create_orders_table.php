<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_reference_number')->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->text('delivery_address');
            $table->foreignId('target_city_id')->constrained('cities');
            $table->decimal('parcel_weight', 8, 2)->default(0);
            $table->json('item_details')->nullable();
            $table->decimal('cod_amount', 12, 2)->default(0);
            $table->enum('payment_method', ['cod', 'bank_transfer', 'manual'])->default('cod');
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending');
            $table->enum('order_status', ['created', 'ready_to_ship', 'dispatched', 'picked_up', 'delivered', 'cancelled'])->default('created');
            $table->enum('merchant_prep_status', ['created', 'label_generated', 'packed'])->default('created');
            $table->string('awb_number')->nullable();
            $table->string('shopify_order_id')->nullable();
            $table->decimal('pickup_lat', 10, 7)->nullable();
            $table->decimal('pickup_lng', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');
        Schema::dropIfExists('orders');
    }
};
