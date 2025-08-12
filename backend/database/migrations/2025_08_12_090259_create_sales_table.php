<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_profit_center_id');
            $table->foreign('client_profit_center_id')
                ->references('id')
                ->on('client_profit_center')
                ->onDelete('cascade');

            $table->unsignedTinyInteger('sales_month'); // 1..12
            $table->unsignedSmallInteger('sales_year');
            $table->unsignedBigInteger('volume');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};