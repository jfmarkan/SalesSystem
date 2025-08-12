<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('profit_centers', function (Blueprint $t) {
            // Código de 3 dígitos
            $t->unsignedSmallInteger('profit_center_code')->primary();

            $t->string('profit_center_name', 255);

            // FK a seasonalities.id
            $t->foreignId('seasonality_id')
              ->constrained('seasonalities')
              ->cascadeOnUpdate()
              ->restrictOnDelete();

            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profit_centers');
    }
};
