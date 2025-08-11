<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('kpis', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();  // e.g. 'users','revenue'
            $table->string('name', 120);
            $table->string('unit', 40)->nullable(); // e.g. 'USD','%','qty'
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('kpi_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_id')->constrained('kpis')->cascadeOnDelete();
            $table->decimal('value', 20, 4);
            $table->timestamp('captured_at')->index();
            $table->json('meta')->nullable(); // source, tags…
            $table->timestamps();

            $table->index(['kpi_id', 'captured_at']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('kpi_values');
        Schema::dropIfExists('kpis');
    }
};
