<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('teams', function (Blueprint $table) {
            // 🔥 Drop existing FK
            $table->dropForeign(['manager_user_id']);

            // 🔄 Make column nullable
            $table->unsignedBigInteger('manager_user_id')->nullable()->change();

            // ✅ Re-attach foreign key with SET NULL on delete
            $table->foreign('manager_user_id')
                ->references('id')->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['manager_user_id']);
            $table->unsignedBigInteger('manager_user_id')->nullable(false)->change();
            $table->foreign('manager_user_id')
                ->references('id')->on('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }
};
