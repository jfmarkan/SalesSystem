<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('forecasts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profit_center_code');
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedTinyInteger('month');
            $table->bigInteger('forecast_value')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index('profit_center_code', 'idx_forecasts_profit_center_code');

            $table->foreign('profit_center_code')
                  ->references('code')
                  ->on('profit_centers')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecasts');
    }
};


