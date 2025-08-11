<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('seasonalities', function (Blueprint $table) {
            $table->id(); // Access: ID (AutoNumber)
            $table->unsignedBigInteger('profit_center_code')->nullable(); // FK added later
            $table->unsignedSmallInteger('fiscal_year')->nullable();

            // Integers only (no decimals)
            $table->unsignedInteger('apr')->default(0);
            $table->unsignedInteger('may')->default(0);
            $table->unsignedInteger('jun')->default(0);
            $table->unsignedInteger('jul')->default(0);
            $table->unsignedInteger('aug')->default(0);
            $table->unsignedInteger('sep')->default(0);
            $table->unsignedInteger('oct')->default(0);
            $table->unsignedInteger('nov')->default(0);
            $table->unsignedInteger('dec')->default(0);
            $table->unsignedInteger('jan')->default(0);
            $table->unsignedInteger('feb')->default(0);
            $table->unsignedInteger('mar')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index('profit_center_code', 'idx_seasonalities_profit_center_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seasonalities');
    }
};
