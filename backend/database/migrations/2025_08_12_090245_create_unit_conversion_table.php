<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->string('profit_center_code', 50)->index();
            $table->string('from_unit');
            $table->decimal('factor_to_m3', 18, 10)->default(1);
            $table->decimal('factor_to_euro', 18, 10)->default(1);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void {
        Schema::dropIfExists('unit_conversions');
    }
};
