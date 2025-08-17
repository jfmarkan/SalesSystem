<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $t) {
            $t->bigIncrements('id');

            $t->unsignedBigInteger('client_profit_center_id');
            $t->foreign('client_profit_center_id')
              ->references('id')->on('client_profit_centers')
              ->cascadeOnUpdate()->restrictOnDelete();

            $t->unsignedSmallInteger('fiscal_year'); // ej: 2026
            $t->unsignedTinyInteger('month');        // 1..12
            $t->unsignedInteger('volume');           // enteros del ERP

            $t->timestamps();
            $t->softDeletes();

            $t->unique(['client_profit_center_id','fiscal_year','month'],'uniq_sales_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
