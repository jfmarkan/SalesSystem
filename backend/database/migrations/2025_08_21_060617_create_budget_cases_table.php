<?php
// database/migrations/2025_08_21_000000_create_budget_cases_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('budget_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_profit_center_id')
                  ->constrained('client_profit_centers')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();
            $table->unsignedSmallInteger('fiscal_year');      // e.g. 2026
            $table->decimal('best_case', 6, 2)->default(0);   // %
            $table->decimal('worst_case', 6, 2)->default(0);  // %
            $table->timestamps();

            $table->unique(['client_profit_center_id','fiscal_year'], 'uniq_budget_case_per_fy');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_cases');
    }
};