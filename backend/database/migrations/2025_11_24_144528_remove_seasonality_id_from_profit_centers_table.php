<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profit_centers', function (Blueprint $table) {
            // Si tenías una foreign key a seasonalities, la borramos primero.
            // OJO: si no existe, podés comentar esta línea.
            try {
                $table->dropForeign(['seasonality_id']);
            } catch (\Throwable $e) {
                // ignoramos si no existe la FK
            }

            if (Schema::hasColumn('profit_centers', 'seasonality_id')) {
                $table->dropColumn('seasonality_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('profit_centers', function (Blueprint $table) {
            // Volvemos a crear la columna por si hay rollback
            $table->unsignedBigInteger('seasonality_id')->nullable();

            // Si querés restaurar también la FK:
            $table->foreign('seasonality_id')
                ->references('id')
                ->on('seasonalities')
                ->nullOnDelete();
        });
    }
};
