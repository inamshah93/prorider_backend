<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('assignment_status')->nullable()->after('rider_id');
            $table->string('pod_photo_path')->nullable();
            $table->string('signature_path')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('failed_at')->nullable();
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY order_status ENUM(
                'created','ready_to_ship','dispatched','picked_up','delivered','cancelled','failed','returned'
            ) NOT NULL DEFAULT 'created'");
        }

        Schema::create('rider_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_profile_id')->constrained('rider_profiles')->cascadeOnDelete();
            $table->string('document_type');
            $table->string('file_path');
            $table->string('status')->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('score');
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->decimal('delivery_surcharge', 10, 2)->default(0)->after('is_active');
            $table->decimal('weight_rate_per_kg', 10, 2)->default(0)->after('delivery_surcharge');
        });
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn(['delivery_surcharge', 'weight_rate_per_kg']);
        });

        Schema::dropIfExists('delivery_ratings');
        Schema::dropIfExists('rider_documents');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'assignment_status',
                'pod_photo_path',
                'signature_path',
                'failure_reason',
                'failed_at',
            ]);
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY order_status ENUM(
                'created','ready_to_ship','dispatched','picked_up','delivered','cancelled'
            ) NOT NULL DEFAULT 'created'");
        }
    }
};
