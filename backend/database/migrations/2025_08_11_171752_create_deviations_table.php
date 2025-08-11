<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('deviations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('forecast_id');
            $table->date('deviation_date');
            $table->bigInteger('deviation_value')->default(0);
            $table->text('reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('forecast_id', 'idx_deviations_forecast_id');

            $table->foreign('forecast_id')
                  ->references('id')
                  ->on('forecasts')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deviations');
    }
};

