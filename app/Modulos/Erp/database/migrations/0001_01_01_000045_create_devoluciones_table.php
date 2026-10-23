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
        Schema::create('devoluciones', function (Blueprint $table) {
            // Movimiento físico de la devolución; la parte tributaria va en la nota de crédito
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('almacen_id')->constrained('almacenes')->restrictOnDelete();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->restrictOnDelete();
            $table->foreignId('compra_id')->nullable()->constrained('compras')->restrictOnDelete();
            $table->foreignId('nota_id')->nullable()->constrained('notas_credito_debito')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('serie', 20);
            $table->unsignedBigInteger('numero');
            $table->enum('tipo', ['devolucion_venta', 'devolucion_compra']);
            $table->enum('estado', ['borrador', 'confirmada', 'anulada'])->default('borrador');
            $table->dateTime('fecha_movimiento');
            $table->text('motivo')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'serie', 'numero'], 'devoluciones_empresa_serie_numero_unique');
            $table->index(['empresa_id', 'fecha_movimiento']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devoluciones');
    }
};
