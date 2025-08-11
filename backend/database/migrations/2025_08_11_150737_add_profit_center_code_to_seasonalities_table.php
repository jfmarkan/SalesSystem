<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('seasonalities', function (Blueprint $table) {
            $table->foreign('profit_center_code', 'fk_seasonalities_profit_center_code')
                  ->references('profit_center_code')->on('profit_centers')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('seasonalities', function (Blueprint $table) {
            $table->dropForeign('fk_seasonalities_profit_center_code');
        });
    }
};
