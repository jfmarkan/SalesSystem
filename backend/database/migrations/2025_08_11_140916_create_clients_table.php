<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('clients', function (Blueprint $t) {
            // Sin columna id
            $t->unsignedInteger('client_group_number');
            $t->string('client_name', 255);
            $t->foreignId('classification_id')
              ->constrained('classifications')   // -> classifications.id
              ->cascadeOnUpdate()
              ->restrictOnDelete();

            $t->timestamps();
            $t->softDeletes();

            // PK exactamente sobre client_group_number
            $t->primary('client_group_number');
        });
    }
    public function down(): void {
        Schema::dropIfExists('clients');
    }
};
