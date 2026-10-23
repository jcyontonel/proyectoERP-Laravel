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
        Schema::create('detalle_ordenes_compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_compra_id')->constrained('ordenes_compra')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->string('descripcion');
            $table->decimal('cantidad', 14, 4);
            $table->decimal('cantidad_recibida', 14, 4)->default(0);       // suma de lo ingresado con compras
            $table->decimal('costo_unitario', 14, 6);                       // sin IGV
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total_impuestos', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['orden_compra_id', 'producto_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_ordenes_compra');
    }
};
