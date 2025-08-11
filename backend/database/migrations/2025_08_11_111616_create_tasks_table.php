<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->enum('status', ['open','in_progress','done','archived'])->default('open')->index();
            $table->timestamp('due_at')->nullable()->index();
            $table->json('meta')->nullable(); // assignees, priority, etc.
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index(['dashboard_id', 'order']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('tasks');
    }
};
