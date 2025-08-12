<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('team_members', function (Blueprint $t) {
            $t->foreignId('team_id')->constrained('teams')->cascadeOnUpdate()->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $t->enum('role', ['MANAGER','SALES_REP','KAM']);
            $t->timestamps();
            $t->softDeletes();
            $t->primary(['team_id','user_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('team_members'); }
};
