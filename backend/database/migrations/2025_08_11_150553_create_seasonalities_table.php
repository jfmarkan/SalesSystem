<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('seasonalities', function (Blueprint $table) {
            // PK: ID (AutoNumber)
            $table->id();

            // ProfitCenterCode: Long Integer, Nullable (Not Enforced)
            $table->unsignedBigInteger('profit_center_code')->nullable();

            // FiscalYear: Long Integer, Nullable
            $table->unsignedBigInteger('fiscal_year')->nullable();

            // Monthly weights (Double with 2 decimals in Access -> decimal here)
            $table->decimal('apr', 12, 2)->nullable();
            $table->decimal('may', 12, 2)->nullable();
            $table->decimal('jun', 12, 2)->nullable();
            $table->decimal('jul', 12, 2)->nullable();
            $table->decimal('aug', 12, 2)->nullable();
            $table->decimal('sep', 12, 2)->nullable();
            $table->decimal('oct', 12, 2)->nullable();
            $table->decimal('nov', 12, 2)->nullable();
            $table->decimal('dec', 12, 2)->nullable();
            $table->decimal('jan', 12, 2)->nullable();
            $table->decimal('feb', 12, 2)->nullable();
            $table->decimal('mar', 12, 2)->nullable();

            // Audit
            $table->timestamps();
            $table->softDeletes();

            // Indexes per Documenter
            $table->index('profit_center_code', 'idx_seasonalities_profit_center_code');

            // Optional FK (Not Enforced in Access)
            $table->foreign('profit_center_code')
                  ->references('profit_center_code')
                  ->on('profit_centers')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seasonalities');
    }
};