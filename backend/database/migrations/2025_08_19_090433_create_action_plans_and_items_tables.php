<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('action_plans', function (Blueprint $t) {
            $t->bigIncrements('id');

            $t->foreignId('deviation_id')
              ->constrained('deviations')
              ->cascadeOnUpdate()
              ->cascadeOnDelete();

            $t->foreignId('user_id')->nullable()
              ->constrained('users')
              ->cascadeOnUpdate()
              ->nullOnDelete();

            // Keep it simple: textual objective (from "plan" textarea)
            $t->text('objective')->nullable();

            $t->boolean('is_completed')->default(false)->index();

            $t->timestamps();

            $t->unique('deviation_id'); // 1 plan per deviation
            $t->index(['user_id']);
        });

        Schema::create('action_items', function (Blueprint $t) {
            $t->bigIncrements('id');

            $t->foreignId('action_plan_id')
              ->constrained('action_plans')
              ->cascadeOnUpdate()
              ->cascadeOnDelete();

            // Minimal fields required by UI/calendar
            $t->string('title');           // required
            $t->text('description')->nullable();
            $t->date('due_date')->nullable();

            $t->boolean('is_completed')->default(false)->index();

            $t->timestamps();

            $t->index(['due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_items');
        Schema::dropIfExists('action_plans');
    }
};