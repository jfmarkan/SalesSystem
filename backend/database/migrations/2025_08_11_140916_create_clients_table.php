<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id('client_group_number');

            $table->string('client_name', 255)->nullable();

            // Required FK to classifications
            $table->foreignId('classification_id')
                ->constrained('classifications')
                ->cascadeOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('classification_id', 'idx_clients_classification_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};

