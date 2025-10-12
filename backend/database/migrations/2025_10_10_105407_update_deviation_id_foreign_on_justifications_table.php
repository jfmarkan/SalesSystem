<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateDeviationIdForeignOnJustificationsTable extends Migration
{
    public function up()
    {
        Schema::table('justifications', function (Blueprint $table) {
            // Eliminar la foreign key existente
            $table->dropForeign(['deviation_id']);

            // Hacer el campo nullable (si no lo es ya)
            $table->unsignedBigInteger('deviation_id')->nullable()->change();

            // Crear la nueva foreign key con nullOnDelete()
            $table->foreign('deviation_id')
                  ->references('id')->on('deviations')
                  ->cascadeOnUpdate()
                  ->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('justifications', function (Blueprint $table) {
            // Revertir a cascadeOnDelete()
            $table->dropForeign(['deviation_id']);
            $table->unsignedBigInteger('deviation_id')->change(); // Quitar nullable si antes no lo era
            $table->foreign('deviation_id')
                  ->references('id')->on('deviations')
                  ->cascadeOnUpdate()
                  ->cascadeOnDelete();
        });
    }
}