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
        Schema::create('existencias_lote', function (Blueprint $table) {
            // Cantidad de cada lote en cada almacén
            $table->id();
            $table->foreignId('lote_id')->constrained('lotes')->restrictOnDelete();
            $table->foreignId('almacen_id')->constrained('almacenes')->restrictOnDelete();
            $table->decimal('cantidad', 14, 4)->default(0);
            $table->decimal('costo_unitario', 14, 6)->default(0);
            $table->timestamps();

            $table->unique(['lote_id', 'almacen_id'], 'existencias_lote_lote_almacen_unique');
            $table->index(['almacen_id', 'lote_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('existencias_lote');
    }
};
