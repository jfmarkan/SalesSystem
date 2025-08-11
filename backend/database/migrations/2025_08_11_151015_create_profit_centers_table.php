<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('profit_centers', function (Blueprint $table) {
            // PK: ProfitCenterCode (Long Integer, Required)
            $table->id('profit_center_code');

            // ProfitCenterName: Short Text (255), Nullable
            $table->string('profit_center_name', 255)->nullable();

            // SeasonalityID: Long Integer, Nullable (relationship not enforced in Access)
            $table->unsignedBigInteger('seasonality_id')->nullable();

            // Audit
            $table->timestamps();
            $table->softDeletes();

            // Indexes (per Documenter)
            $table->index('profit_center_code', 'idx_profit_center_code');
            $table->index('seasonality_id', 'idx_profit_centers_seasonality_id');

            // FK: optional (Not Enforced in Access) - target table name will be created below (seasonalities)
            $table->foreign('seasonality_id')->references('id')->on('seasonalities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profit_centers');
    }
};
