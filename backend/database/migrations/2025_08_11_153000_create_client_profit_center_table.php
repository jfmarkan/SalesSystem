<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('client_profit_centers', function (Blueprint $t) {
            $t->bigIncrements('id'); // identificador único del vínculo

            // FK al PK de clients (client_group_number)
            $t->unsignedInteger('client_group_number');
            $t->foreign('client_group_number')
              ->references('client_group_number')->on('clients')
              ->cascadeOnUpdate()->restrictOnDelete();

            // FK al PK de profit_centers (profit_center_code)
            $t->unsignedSmallInteger('profit_center_code');
            $t->foreign('profit_center_code')
              ->references('profit_center_code')->on('profit_centers')
              ->cascadeOnUpdate()->restrictOnDelete();

            $t->timestamps();
            $t->softDeletes();

            $t->unique(['client_group_number','profit_center_code'],'uniq_client_pc');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_profit_centers');
    }
};
