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
        Schema::create('kardex', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('almacen_id')->constrained('almacenes')->restrictOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('lote_id')->nullable()->constrained('lotes')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

            // Documento de referencia (venta, compra, ajuste, transferencia, devolucion, stock_inicial...)
            $table->string('referencia_tipo')->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->string('referencia_serie', 10)->nullable();
            $table->string('referencia_numero', 20)->nullable();

            // Movimiento
            $table->enum('tipo_movimiento', ['ingreso', 'egreso', 'ajuste']);
            $table->decimal('cantidad', 14, 4);
            $table->decimal('costo_unitario', 14, 6)->nullable();
            $table->decimal('costo_total', 14, 2)->nullable();

            // Stock del almacén antes y después
            $table->decimal('stock_anterior', 14, 4);
            $table->decimal('stock_nuevo', 14, 4);

            $table->date('fecha_vencimiento')->nullable();
            $table->dateTime('fecha_movimiento')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'producto_id', 'created_at'], 'idx_kardex_producto');
            $table->index(['empresa_id', 'almacen_id', 'producto_id', 'fecha_movimiento'], 'idx_kardex_almacen_producto');
            $table->index(['referencia_tipo', 'referencia_id'], 'idx_kardex_referencia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kardex');
    }
};
