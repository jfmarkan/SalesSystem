<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('forecasts', function (Blueprint $table) {
            $table->id(); // ID (AutoNumber)
            $table->unsignedBigInteger('assignment_matrix_id'); // Required: True (FK)
            $table->unsignedInteger('forecast_month')->nullable(); // Required: False
            $table->unsignedInteger('forecast_year')->nullable();  // Required: False
            $table->bigInteger('volume')->default(0); // integer-only

            $table->timestamps();
            $table->softDeletes();

            $table->index('assignment_matrix_id', 'idx_forecasts_assignment_matrix_id');

            $table->foreign('assignment_matrix_id')
                  ->references('id')
                  ->on('assignment_matrix')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecasts');
    }
};
