<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->json('opening_hours')->nullable(); // ej: {"mon":["08:00-12:00","14:00-18:00"]}
            $table->integer('class_duration')->default(45); // en minutos
            $table->integer('cooldown_minutes')->default(15);
            $table->integer('available_spots')->default(4);
            $table->foreignId('owner_id')->constrained('users'); // el manager
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
