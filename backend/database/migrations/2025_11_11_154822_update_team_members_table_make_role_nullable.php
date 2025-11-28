<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('team_members', function (Blueprint $table) {
            $table->enum('role', ['MANAGER', 'SALES_REP', 'KAM'])
                  ->nullable()
                  ->change();
        });
    }

    public function down(): void {
        Schema::table('team_members', function (Blueprint $table) {
            $table->enum('role', ['MANAGER', 'SALES_REP', 'KAM'])
                  ->nullable(false)
                  ->change();
        });
    }
};
