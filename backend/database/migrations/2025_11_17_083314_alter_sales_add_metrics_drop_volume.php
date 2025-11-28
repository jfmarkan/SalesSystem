<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $t) {
            // Nuevas columnas
            $t->decimal('cubic_meters', 20, 2)->default(0)->after('month');       // m³
            $t->decimal('sales_units', 20, 2)->default(0)->after('cubic_meters'); // VK-EH
            $t->decimal('euros', 20, 2)->default(0)->after('sales_units');        // €

            // Eliminar la antigua columna volume
            $t->dropColumn('volume');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $t) {
            // Restaurar la columna volume
            $t->integer('volume')->default(0)->after('month');

            // Eliminar las nuevas columnas
            $t->dropColumn(['cubic_meters', 'sales_units', 'euros']);
        });
    }
};
