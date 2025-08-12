<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('seasonalities', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('fiscal_year')->nullable();
            $table->decimal('apr')->default(0);
            $table->decimal('may')->default(0);
            $table->decimal('jun')->default(0);
            $table->decimal('jul')->default(0);
            $table->decimal('aug')->default(0);
            $table->decimal('sep')->default(0);
            $table->decimal('oct')->default(0);
            $table->decimal('nov')->default(0);
            $table->decimal('dec')->default(0);
            $table->decimal('jan')->default(0);
            $table->decimal('feb')->default(0);
            $table->decimal('mar')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seasonalities');
    }
};
