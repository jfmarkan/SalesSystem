<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $t) {
            $t->bigIncrements('id');

            // Relación CPC
            $t->unsignedBigInteger('client_profit_center_id');
            $t->foreign('client_profit_center_id')
              ->references('id')->on('client_profit_centers')
              ->cascadeOnUpdate()->restrictOnDelete();

            // Año fiscal y mes (mes 1-12)
            $t->unsignedSmallInteger('fiscal_year'); // Ej: 2026
            $t->unsignedTinyInteger('month');        // 1=Jan ... 12=Dec

            // Valor presupuestado (entero redondo, sin decimales)
            $t->unsignedInteger('amount');

            $t->timestamps();
            $t->softDeletes();

            // Evitar duplicados de año+mes por CPC
            $t->unique(['client_profit_center_id','fiscal_year','month'],'uniq_budget');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
