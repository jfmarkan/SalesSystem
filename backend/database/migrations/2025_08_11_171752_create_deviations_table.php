<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('deviations', function (Blueprint $t) {
            $t->bigIncrements('id');

            // Ámbito de la desviación (código de Profit Center numérico, 3 cifras)
            $t->unsignedSmallInteger('profit_center_code'); // 0..999

            // Tipo: comparación con presupuesto usando ventas (pasado) o forecast (futuro)
            $t->enum('deviation_type', ['SALES','FORECAST']);

            // Periodo evaluado
            $t->unsignedSmallInteger('fiscal_year');
            $t->unsignedTinyInteger('month'); // 1..12

            // Resultado calculado (porcentaje respecto al budget)
            $t->decimal('deviation_ratio', 5, 2)->nullable(); // ej: 94.50

            // Explicación del usuario cuando supera umbral
            $t->text('explanation')->nullable();

            // Autor
            $t->foreignId('user_id')->nullable()
              ->constrained('users')
              ->cascadeOnUpdate()
              ->nullOnDelete();

            $t->timestamps();
            $t->softDeletes();

            // Índices
            $t->index(['profit_center_code']);
            $t->unique(
                ['profit_center_code', 'fiscal_year', 'month', 'deviation_type', 'user_id'],
                'uniq_dev_pc_user_period_type'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deviations');
    }
};
