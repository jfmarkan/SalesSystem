<?php
// database/migrations/2025_08_21_000110_create_budget_debug_log_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('budget_debug_log', function (Blueprint $table) {
            $table->id();

            // FK to client_profit_centers
            $table->unsignedBigInteger('client_profit_center_id');
            $table->foreign('client_profit_center_id')
                ->references('id')->on('client_profit_centers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Period + distribution fields
            $table->unsignedSmallInteger('fiscal_year');   // target FY (Apr..Mar)
            $table->unsignedSmallInteger('budget_year');   // calendar year
            $table->unsignedTinyInteger('budget_month');   // 1..12
            $table->string('month_name', 3);               // 'Apr'..'Mar'

            // Basis + results
            $table->decimal('sales_volume', 18, 2)->default(0);     // YTD total used as base
            $table->decimal('best_case', 6, 2)->nullable();         // %
            $table->decimal('worst_case', 6, 2)->nullable();        // %
            $table->decimal('seasonality_base', 6, 2)->default(0);  // summed pct YTD
            $table->decimal('forecast_base', 18, 2)->default(0);
            $table->decimal('total_budget', 18, 2)->default(0);
            $table->decimal('monthly_pct', 6, 2)->default(0);
            $table->decimal('monthly_volume', 18, 2)->default(0);

            $table->timestamps();

            $table->index(['client_profit_center_id', 'fiscal_year'], 'idx_cpc_fy');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_debug_log');
    }
};