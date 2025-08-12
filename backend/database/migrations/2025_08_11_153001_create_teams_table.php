<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('teams', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name', 80)->unique();          // hoy: A, B, C
            $t->foreignId('manager_user_id')           // manager obligatorio
              ->constrained('users')
              ->cascadeOnUpdate()
              ->restrictOnDelete();
            $t->timestamps();
            $t->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('teams'); }
};
