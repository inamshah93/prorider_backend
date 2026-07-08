<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_location_pings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_profile_id')->constrained('rider_profiles')->cascadeOnDelete();

            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);

            $table->decimal('accuracy_m', 10, 2)->nullable();
            $table->decimal('speed_mps', 10, 2)->nullable();
            $table->decimal('heading_deg', 10, 2)->nullable();

            // Use recorded_at to support time filtering and route reconstruction.
            $table->timestamp('recorded_at')->index();
            $table->timestamps();

            $table->index(['rider_profile_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_location_pings');
    }
};

