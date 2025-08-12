<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('dashboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->boolean('is_default')->default(false)->index();
            $table->timestamps();
            $table->unique(['user_id','name']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('dashboards');
    }
};
