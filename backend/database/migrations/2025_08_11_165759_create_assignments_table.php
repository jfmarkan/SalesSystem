<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $t) {
            $t->bigIncrements('id');

            // CPC
            $t->unsignedBigInteger('client_profit_center_id');
            $t->foreign('client_profit_center_id')
              ->references('id')->on('client_profit_centers')
              ->cascadeOnUpdate()->restrictOnDelete();

            // Team
            $t->foreignId('team_id')
              ->constrained('teams')
              ->cascadeOnUpdate()->restrictOnDelete();

            // Usuario asignado
            $t->foreignId('user_id')
              ->constrained('users')
              ->cascadeOnUpdate()->restrictOnDelete();

            $t->timestamps();
            $t->softDeletes();

            // Evita duplicados exactos de asignación
            $t->unique(['client_profit_center_id','team_id','user_id'],'uniq_assignment');
            $t->index(['team_id','user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
