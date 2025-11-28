<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('teams', function (Blueprint $table) {
            // Hacemos que manager_user_id sea nullable
            $table->dropForeign(['manager_user_id']);
            $table->foreignId('manager_user_id')
                  ->nullable()
                  ->change();
            $table->foreign('manager_user_id')
                  ->references('id')->on('users')
                  ->cascadeOnUpdate()
                  ->nullOnDelete();

            // Agregamos el company_id
            $table->foreignId('company_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('companies')
                  ->cascadeOnUpdate()
                  ->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('teams', function (Blueprint $table) {
            // Quitamos la foreign key y columna de company_id
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');

            // Revertimos manager_user_id a NOT NULL
            $table->dropForeign(['manager_user_id']);
            $table->foreignId('manager_user_id')
                  ->nullable(false)
                  ->change();
            $table->foreign('manager_user_id')
                  ->references('id')->on('users')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();
        });
    }
};
