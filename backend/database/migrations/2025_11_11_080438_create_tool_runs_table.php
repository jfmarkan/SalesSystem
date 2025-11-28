<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tool_runs', function (Blueprint $t) {
            $t->id();
            $t->string('tool', 64);                      // ej: clients-update
            $t->string('status', 16)->default('queued'); // queued|running|ok|failed
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->json('options')->nullable();
            $t->json('stats')->nullable();
            $t->longText('log')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();
            $t->index(['tool','status']);
            $t->index('created_at');
        });
    }
    public function down(): void { Schema::dropIfExists('tool_runs'); }
};
