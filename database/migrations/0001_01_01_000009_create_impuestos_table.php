<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('impuestos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique();         // IGV, EXO, INA, EXP
            $table->string('nombre', 60);
            $table->string('tipo_afectacion', 2);           // Catálogo SUNAT 07: 10, 20, 30, 40
            $table->string('codigo_tributo', 4);            // Catálogo SUNAT 05: 1000, 9997, 9998, 9995
            $table->decimal('porcentaje', 5, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('impuestos');
    }
};
