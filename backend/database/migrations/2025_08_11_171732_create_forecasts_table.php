<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('forecasts', function (Blueprint $t) {
            $t->bigIncrements('id');

            // Relación al vínculo Cliente+ProfitCenter
            $t->unsignedBigInteger('client_profit_center_id');
            $t->foreign('client_profit_center_id')
              ->references('id')->on('client_profit_centers')
              ->cascadeOnUpdate()->restrictOnDelete();

            // Periodo
            $t->unsignedSmallInteger('fiscal_year');   // ej: 2026
            $t->unsignedTinyInteger('month');          // 1..12

            // Valor pronosticado (entero, sin decimales)
            $t->Integer('volume');

            // Versionado (controlás lógica en controller/frontend)
            $t->unsignedInteger('version')->default(1);

            // Quién lo cargó (opcional)
            $t->foreignId('user_id')->nullable()
              ->constrained('users')
              ->cascadeOnUpdate()
              ->nullOnDelete();

            $t->timestamps();
            $t->softDeletes();

            // Búsquedas rápidas por periodo
            $t->index(['client_profit_center_id','fiscal_year','month']);

            // Evita duplicar la misma versión del mismo periodo
            $t->unique(['client_profit_center_id','fiscal_year','month','version'],'uniq_forecast_version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecasts');
    }
};
