<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->enum('status', ['active','inactive','archived'])->default('active')->index();
            $table->unsignedInteger('order')->default(0);
            $table->json('meta')->nullable(); // tags, links, etc.
            $table->timestamps();

            $table->index(['dashboard_id', 'order']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('list_items');
    }
};
