<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('deviations', function (Blueprint $t) {
            $t->bigIncrements('id');

            // Ámbito de la desviación
            $t->unsignedBigInteger('client_profit_center_id');
            $t->foreign('client_profit_center_id')
              ->references('id')->on('client_profit_centers')
              ->cascadeOnUpdate()->restrictOnDelete();

            // Tipo: comparación con presupuesto usando ventas (pasado) o forecast (futuro)
            $t->enum('deviation_type', ['SALES','FORECAST']);

            // Periodo evaluado
            $t->unsignedSmallInteger('fiscal_year');
            $t->unsignedTinyInteger('month'); // 1..12

            // Resultado calculado (porcentaje respecto al budget)
            $t->decimal('percent_of_budget', 6, 2)->nullable(); // ej: 94.50

            // Explicación del usuario cuando supera umbral
            $t->text('explanation')->nullable();

            // Autor
            $t->foreignId('user_id')->nullable()
              ->constrained('users')
              ->cascadeOnUpdate()
              ->nullOnDelete();

            $t->timestamps();
            $t->softDeletes();

            $t->index(['client_profit_center_id','fiscal_year','month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deviations');
    }
};
