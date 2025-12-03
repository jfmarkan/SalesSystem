<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_cases', function (Blueprint $table) {
            // Change best_case and worst_case to decimal(10,2)
            $table->decimal('best_case', 10, 2)
                  ->default(0)
                  ->change();

            $table->decimal('worst_case', 10, 2)
                  ->default(0)
                  ->change();
        });
    }

    public function down(): void
    {
        Schema::table('budget_cases', function (Blueprint $table) {
            // Revert back to decimal(6,2)
            $table->decimal('best_case', 6, 2)
                  ->default(0)
                  ->change();

            $table->decimal('worst_case', 6, 2)
                  ->default(0)
                  ->change();
        });
    }
};
