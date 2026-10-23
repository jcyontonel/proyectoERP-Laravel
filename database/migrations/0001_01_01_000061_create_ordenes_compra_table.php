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
        Schema::create('ordenes_compra', function (Blueprint $table) {
            // Pedido al proveedor. No mueve stock: se recibe (total o parcialmente) registrando compras.
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
            $table->foreignId('almacen_id')->constrained('almacenes')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('serie', 10);
            $table->unsignedBigInteger('numero');
            $table->dateTime('fecha_emision');
            $table->date('fecha_entrega')->nullable();
            $table->string('moneda', 3)->default('PEN');
            $table->string('condicion_pago', 12)->default('contado');      // contado | credito
            $table->boolean('afecto_igv')->default(true);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total_impuestos', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('estado', 12)->default('emitida');              // emitida | parcial | recibida | cerrada | anulada
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('moneda')->references('codigo')->on('monedas')->restrictOnDelete()->cascadeOnUpdate();
            $table->unique(['empresa_id', 'serie', 'numero'], 'uniq_orden_compra_empresa');
            $table->index(['empresa_id', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ordenes_compra');
    }
};
