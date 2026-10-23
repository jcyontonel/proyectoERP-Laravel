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
        Schema::create('unidades_conversion', function (Blueprint $table) {
            // 1 unidad_origen = factor x unidad_destino. Dos usos:
            //  - presentación (producto_destino_id NULL): el mismo producto en otra unidad (1 Caja = 12 Unidad)
            //  - fraccionamiento (producto_destino_id): 1 Saco del producto se parte en factor unidades de OTRO
            //    producto (1 "Ricocan 15 kg" = 15 Kilogramo de "Ricocan por kilo")
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('producto_destino_id')->nullable()->constrained('productos')->cascadeOnDelete();
            $table->foreignId('unidad_origen_id')->constrained('tipo_unidades')->restrictOnDelete();
            $table->foreignId('unidad_destino_id')->constrained('tipo_unidades')->restrictOnDelete();
            $table->decimal('factor', 14, 6);
            $table->decimal('precio_venta', 12, 2)->nullable(); // NULL = factor x precio de la unidad base
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['producto_id', 'unidad_origen_id', 'unidad_destino_id', 'producto_destino_id'], 'unidades_conversion_producto_unique');
            $table->index(['producto_destino_id', 'activo'], 'unidades_conversion_destino_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unidades_conversion');
    }
};
