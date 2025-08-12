<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('client_profit_center', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('client_group_number');
            $table->unsignedBigInteger('profit_center_code');

            $table->timestamps();
            $table->softDeletes();

            $table->index('client_group_number', 'idx_cpcp_client_group_number');
            $table->index('profit_center_code', 'idx_cpcp_profit_center_code');

            // Relaciones (Not Enforced en Access → aquí opcionales)
            $table->foreign('client_group_number')
                  ->references('client_group_number')
                  ->on('clients')
                  ->cascadeOnDelete();

            $table->foreign('profit_center_code')
                  ->references('profit_center_code')
                  ->on('profit_centers')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_profit_center_pivots');
    }
};

