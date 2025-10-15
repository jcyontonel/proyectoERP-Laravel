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

            // 🔗 Relaciones principales
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->foreignId('producto_id')->constrained('productos')->onDelete('cascade');

            // 📄 Documento de referencia
            $table->enum('tipo_movimiento', ['ingreso', 'egreso']);
            $table->string('motivo')->nullable(); // compra, venta, ajuste, devolución, etc.
            $table->string('referencia_tipo')->nullable(); // 'compra' o 'venta'
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->string('referencia_serie', 10)->nullable();
            $table->string('referencia_numero', 20)->nullable();

            // 📦 Movimiento
            $table->decimal('cantidad', 12, 2);
            $table->decimal('costo_unitario', 12, 2)->nullable();
            $table->decimal('costo_total', 14, 2)->nullable();

            // 📊 Saldo resultante
            $table->decimal('saldo_cantidad', 12, 2)->nullable();
            $table->decimal('saldo_valorizado', 14, 2)->nullable();

            $table->text('observacion')->nullable();

            $table->timestamps();
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
