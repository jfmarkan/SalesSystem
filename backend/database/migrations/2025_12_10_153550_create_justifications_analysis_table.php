<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('justifications_analysis', function (Blueprint $table) {
            $table->id();

            // Vendedor al que se refiere la desviación
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Profitcenter code como INTEGER
            $table->unsignedInteger('pc_code')
                ->nullable()
                ->index();

            // Periodo
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');

            // Tipo de desvío (por ahora solo forecast, pero lo dejamos algo flexible)
            $table->string('type', 20)->default('forecast');

            // Texto del manager
            $table->text('note');

            // Manager que escribió la nota
            $table->foreignId('manager_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('justifications_analysis');
    }
};
