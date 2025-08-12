<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id(); // ID (AutoNumber)
            $table->unsignedBigInteger('client_profit_center_id'); // Required: True (Access)
            $table->unsignedInteger('budget_month')->nullable();   // Required: False
            $table->unsignedInteger('budget_year')->nullable();    // Required: False
            $table->unsignedBigInteger('volume');                  // Required: True

            $table->timestamps();
            $table->softDeletes();

            $table->index('client_profit_center_id', 'idx_budgets_client_profit_center_id');

            $table->foreign('client_profit_center_id')
                  ->references('id')
                  ->on('client_profit_center')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};