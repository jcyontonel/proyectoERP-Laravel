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
        Schema::create('almacen_producto', function (Blueprint $table) {
            // Stock actual de cada producto en cada almacén (solo lo mueve InventarioService)
            $table->id();
            $table->foreignId('almacen_id')->constrained('almacenes')->restrictOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->decimal('stock', 14, 4)->default(0);
            $table->decimal('stock_minimo', 14, 4)->default(0);
            $table->timestamps();

            $table->unique(['almacen_id', 'producto_id'], 'uniq_almacen_producto');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('almacen_producto');
    }
};
