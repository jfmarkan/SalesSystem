<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('profit_centers', function (Blueprint $table) {
            // Access PK: ProfitCenterCode (Long Integer, NOT auto-increment)
            $table->unsignedBigInteger('profit_center_code');
            $table->primary('profit_center_code');

            $table->string('profit_center_name', 255)->nullable();

            // Access SeasonalityID (Not Enforced) → nullable FK
            $table->unsignedBigInteger('seasonality_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('seasonality_id', 'idx_profit_centers_seasonality_id');

            $table->foreign('seasonality_id')
                  ->references('id')->on('seasonalities')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profit_centers');
    }
};
