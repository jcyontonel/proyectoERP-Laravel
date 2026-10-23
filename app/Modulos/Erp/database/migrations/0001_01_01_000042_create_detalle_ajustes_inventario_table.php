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
        Schema::create('detalle_ajustes_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ajuste_id')->constrained('ajustes_inventario')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('lote_id')->nullable()->constrained('lotes')->restrictOnDelete();
            $table->decimal('stock_sistema', 14, 4)->nullable(); // conteo: lo que decía el sistema
            $table->decimal('stock_contado', 14, 4)->nullable(); // conteo: lo que se contó
            $table->decimal('cantidad', 14, 4);                  // movimiento resultante
            $table->decimal('costo_unitario', 14, 6)->default(0);
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['ajuste_id', 'producto_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_ajustes_inventario');
    }
};
