<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_id')->constrained()->cascadeOnDelete();
            $table->foreignId('widget_id')->constrained()->cascadeOnDelete();

            // Grid layout geometry (vue3-grid-layout)
            $table->integer('x')->default(0);
            $table->integer('y')->default(0);
            $table->integer('w')->default(2);
            $table->integer('h')->default(4);
            $table->string('i', 50)->nullable(); // client key (e.g. "7")

            // Per-instance props (e.g., kpiId, filters, theme)
            $table->json('props')->nullable();

            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['dashboard_id', 'sort']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('dashboard_widgets');
    }
};
