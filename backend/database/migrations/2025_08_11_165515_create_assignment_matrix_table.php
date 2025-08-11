<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('assignment_matrix', function (Blueprint $table) {
            $table->id(); // ID (PK, Auto Increment)

            // FK to user_manager_pivot (nullable porque en Access es Not Enforced y no Required)
            $table->unsignedBigInteger('user_manager_id')->nullable();

            // FK to client_profit_center_pivot (nullable)
            $table->unsignedBigInteger('client_profit_center_id')->nullable();

            // Auditoría
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('user_manager_id', 'idx_assignment_matrices_user_manager_id');
            $table->index('client_profit_center_id', 'idx_assignment_matrices_client_profit_center_id');

            // Foreign keys (no enforced en Access, pero aquí las podemos forzar si existen tablas)
            $table->foreign('user_manager_id')
                  ->references('id')
                  ->on('user_manager_pivots')
                  ->nullOnDelete();

            $table->foreign('client_profit_center_id')
                  ->references('id')
                  ->on('client_profit_center_pivots')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_matrix');
    }
};

