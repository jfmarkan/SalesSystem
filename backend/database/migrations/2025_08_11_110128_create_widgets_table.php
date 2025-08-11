<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('widgets', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32); // 'kpi','chart','task','list','calendar'
            $table->string('title', 150)->nullable();
            $table->json('config')->nullable(); // component-specific config (e.g., for charts)
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['type']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('widgets');
    }
};
