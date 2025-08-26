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
            $table->smallInteger('fiscal_year');
            $table->unsignedSmallInteger('profit_center_code');
            $table->unsignedInteger('volume')->default(0);
            $table->timestamps();
            $table->unique(['fiscal_year','profit_center_code'], 'uq_eq_av_fy_pc');
        });

        // 2) extra_quota_assignments
        Schema::create('extra_quota_assignments', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('fiscal_year');
            $table->unsignedSmallInteger('profit_center_code');
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedInteger('volume')->default(0);
            $table->boolean('is_published')->default(false)->index();
            $table->date('assignment_date')->nullable();
            $table->timestamps();
            $table->unique(['fiscal_year','profit_center_code','user_id'], 'uq_eq_asg_fy_pc_user');
        });

        // 3) sales_opportunities
        Schema::create('sales_opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->smallInteger('fiscal_year');
            $table->unsignedSmallInteger('profit_center_code');
            $table->unsignedInteger('volume')->default(0);
            $table->unsignedTinyInteger('probability_pct')->default(0);
            $table->date('estimated_start_date')->nullable();
            $table->text('comments')->nullable();
            $table->enum('status', ['draft','open','won','lost'])->default('open');
            $table->unsignedBigInteger('opportunity_group_id')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('potential_client_name', 255)->nullable();
            $table->boolean('is_won')->default(false)->index();
            $table->timestamp('won_at')->nullable();
            $table->boolean('is_lost')->default(false)->index();
            $table->timestamp('lost_at')->nullable();
            $table->string('client_group_number', 100)->nullable();
            $table->timestamps();

            $table->index(['opportunity_group_id','version'], 'ix_so_grp_ver');
            $table->index(['user_id','fiscal_year','profit_center_code'], 'ix_so_user_fy_pc');
        });

        // 4) extra_quota_budget
        Schema::create('extra_quota_budgets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('opportunity_group_id');
            $table->unsignedInteger('version');
            $table->unsignedTinyInteger('month');    // 1..12
            $table->smallInteger('fiscal_year');     // FY del mes
            $table->unsignedInteger('volume')->default(0);
            $table->timestamp('calculation_date')->nullable();
            $table->timestamps();
            $table->unique(['opportunity_group_id','version','fiscal_year','month'], 'uq_budget_grp_ver_fy_m');
        });

        // 5) extra_quota_forecasts
        Schema::create('extra_quota_forecasts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('opportunity_group_id');
            $table->unsignedInteger('version');
            $table->unsignedTinyInteger('month'); // 1..12
            $table->smallInteger('fiscal_year');
            $table->unsignedInteger('volume')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->unique(['opportunity_group_id','version','fiscal_year','month'], 'uq_forecast_grp_ver_fy_m');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_quota_forecasts');
        Schema::dropIfExists('extra_quota_budgets');
        Schema::dropIfExists('sales_opportunities');
        Schema::dropIfExists('extra_quota_assignments');
        Schema::dropIfExists('extra_quota_available');
    }
};