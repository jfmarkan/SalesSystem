<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profit_center_code');
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedBigInteger('budgeting_case_id');
            $table->bigInteger('amount')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index('profit_center_code', 'idx_budgets_profit_center_code');
            $table->index('budgeting_case_id', 'idx_budgets_budgeting_case_id');

            $table->foreign('profit_center_code')
                  ->references('code')
                  ->on('profit_centers')
                  ->cascadeOnDelete();

            $table->foreign('budgeting_case_id')
                  ->references('id')
                  ->on('budgeting_cases')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
