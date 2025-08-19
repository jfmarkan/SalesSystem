<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('justifications', function (Blueprint $t) {
            $t->bigIncrements('id');

            $t->foreignId('deviation_id')
              ->constrained('deviations')
              ->cascadeOnUpdate()
              ->cascadeOnDelete();

            $t->foreignId('user_id')->nullable()
              ->constrained('users')
              ->cascadeOnUpdate()
              ->nullOnDelete();

            $t->enum('type', ['SALES','FORECAST']);
            $t->text('comment')->nullable(); // sales uses comment; forecast also has comment
            $t->text('plan')->nullable();    // forecast textual plan (objective)

            $t->timestamps();

            $t->unique('deviation_id'); // 1:1
            $t->index(['user_id','type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('justifications');
    }
};
