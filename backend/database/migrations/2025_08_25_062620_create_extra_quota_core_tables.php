<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        // 1) extra_quota_available
        Schema::create('extra_quota_available', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('fiscal_year');                  // e.g. 2025
            $table->string('profit_center_code', 50);             // PC code
            $table->decimal('volume', 15, 2)->default(0);         // total extra quota volume (units)
            $table->timestamps();

            $table->unique(['fiscal_year','profit_center_code']);
        });

        // 2) extra_quota_assignment
        Schema::create('extra_quota_assignment', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('fiscal_year');
            $table->string('profit_center_code', 50);
            $table->foreignId('user_id')->constrained('users');
            $table->decimal('quota_volume', 15, 2)->default(0);
            $table->boolean('is_published')->default(false);
            $table->date('assignment_date')->nullable();
            $table->timestamps();

            $table->unique(['fiscal_year','profit_center_code','user_id']);
        });

        // 3) sales_opportunities
        Schema::create('sales_opportunities', function (Blueprint $table) {
            $table->id(); // autoincrement

            $table->foreignId('user_id')->constrained('users');   // owner (sales rep)
            $table->smallInteger('fiscal_year');
            $table->string('profit_center_code', 50);

            $table->decimal('opportunity_amount', 15, 2)->default(0);
            $table->unsignedTinyInteger('probability_pct')->default(0);
            $table->date('estimated_start_date')->nullable();
            $table->text('comments')->nullable();

            $table->enum('status', ['draft','open','won','lost'])->default('open');

            // versioning
            $table->unsignedBigInteger('opportunity_group_id')->nullable(); // set to id on first insert
            $table->unsignedInteger('version')->default(1);

            $table->string('potential_client_name', 255)->nullable();

            $table->boolean('is_won')->default(false);
            $table->timestamp('won_at')->nullable();
            $table->boolean('is_lost')->default(false);
            $table->timestamp('lost_at')->nullable();

            $table->string('client_group_number', 100)->nullable();

            $table->timestamps(); // includes created_at

            $table->index(['opportunity_group_id','version']);
            $table->index(['user_id','fiscal_year','profit_center_code']);
        });

        // 4) extra_quota_budget
        Schema::create('extra_quota_budget', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('opportunity_group_id');
            $table->unsignedInteger('version');
            $table->unsignedTinyInteger('month');    // 1..12 calendar month
            $table->smallInteger('fiscal_year');     // fiscal year this month belongs to
            $table->decimal('volume', 15, 2)->default(0);
            $table->timestamp('calculation_date')->nullable(); // can be timestamps

            $table->timestamps();

            $table->index(['opportunity_group_id','version','fiscal_year','month']);
            $table->unique(['opportunity_group_id','version','fiscal_year','month'], 'uq_budget_group_ver_fy_m');
        });

        // 5) extra_quota_forecasts
        Schema::create('extra_quota_forecasts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('opportunity_group_id');
            $table->unsignedInteger('version');          // forecast tied to opp version (not independently versioned)
            $table->unsignedTinyInteger('month');        // 1..12 calendar month
            $table->smallInteger('fiscal_year');
            $table->decimal('volume', 15, 2)->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['opportunity_group_id','version','fiscal_year','month']);
            $table->unique(['opportunity_group_id','version','fiscal_year','month'], 'uq_forecast_group_ver_fy_m');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_quota_forecasts');
        Schema::dropIfExists('extra_quota_budget');
        Schema::dropIfExists('sales_opportunities');
        Schema::dropIfExists('extra_quota_assignment');
        Schema::dropIfExists('extra_quota_available');
    }
};
