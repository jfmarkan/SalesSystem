<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('profit_centers', function (Blueprint $table) {
            $table->primary('profit_center_code');
            $table->string('profit_center_name', 255);
            $table->foreignId('seasonality_id')->constrained('seasonalities')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profit_centers');
    }
};
