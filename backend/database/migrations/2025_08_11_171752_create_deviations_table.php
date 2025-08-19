<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('deviations', function (Blueprint $t) {
            $t->bigIncrements('id');

            // Scope / identification
            $t->unsignedSmallInteger('profit_center_code'); // 0..999
            $t->string('pc_name')->nullable();              // optional PC readable name snapshot
            $t->string('client_name')->nullable();          // optional client name snapshot

            // Type: SALES (historical vs budget) / FORECAST (future vs budget)
            $t->enum('deviation_type', ['SALES', 'FORECAST']);

            // Period
            $t->unsignedSmallInteger('fiscal_year');
            $t->unsignedTinyInteger('month'); // 1..12

            // Core metrics (widened precision)
            $t->decimal('sales', 20, 2)->nullable();
            $t->decimal('budget', 20, 2)->nullable();
            $t->decimal('forecast', 20, 2)->nullable();

            // Derived deltas (widened precision)
            $t->decimal('delta_abs', 20, 4)->nullable(); // ref - budget
            $t->decimal('delta_pct', 12, 6)->nullable(); // (delta_abs / budget) * 100

            // Optional ratio kept for compatibility (percentage)
            $t->decimal('deviation_ratio', 12, 6)->nullable();

            // Series for charts
            $t->json('months')->nullable();           // ["YYYY-MM", ...]
            $t->json('sales_series')->nullable();     // [number,...]
            $t->json('budget_series')->nullable();    // [number,...]
            $t->json('forecast_series')->nullable();  // [number,...]

            // State
            $t->boolean('justified')->default(false);

            // Author
            $t->foreignId('user_id')->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $t->timestamps();
            $t->softDeletes();

            // Indexes
            $t->index(['user_id']);
            $t->index(['profit_center_code']);
            $t->unique(
                ['profit_center_code', 'fiscal_year', 'month', 'deviation_type', 'user_id'],
                'uniq_dev_pc_user_period_type'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deviations');
    }
};