<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name', 125)->nullable()->index();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject', 125);
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer', 125);
            $table->json('attribute_changes')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();
        });
    }
};
