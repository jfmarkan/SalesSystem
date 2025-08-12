
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('seasonalities', function (Blueprint $t) {
            $t->bigIncrements('id');

            // PC code de 3 dígitos (sin FK por ahora, tipo entero)
            $t->unsignedSmallInteger('profit_center_code'); // 0..999

            // Año fiscal de 4 dígitos
            $t->unsignedSmallInteger('fiscal_year'); // p.ej. 2026

            // Meses (porcentaje 0.00..100.00)
            $t->decimal('apr', 5, 2);
            $t->decimal('may', 5, 2);
            $t->decimal('jun', 5, 2);
            $t->decimal('jul', 5, 2);
            $t->decimal('aug', 5, 2);
            $t->decimal('sep', 5, 2);
            $t->decimal('oct', 5, 2);
            $t->decimal('nov', 5, 2);
            $t->decimal('dec', 5, 2);
            $t->decimal('jan', 5, 2);
            $t->decimal('feb', 5, 2);
            $t->decimal('mar', 5, 2);

            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seasonalities');
    }
};
