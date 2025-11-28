<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'group_responsible')) {
                $table->string('group_responsible', 64)->nullable()->after('classification_id');
                $table->index('group_responsible', 'idx_clients_group_responsible');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'group_responsible')) {
                $table->dropIndex('idx_clients_group_responsible');
                $table->dropColumn('group_responsible');
            }
        });
    }
};
